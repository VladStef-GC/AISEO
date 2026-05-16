<?php

/**
 * Standalone AI Hallucination Test for Site Chat
 *
 * This script boots WordPress, collects ground-truth data, sends test prompts
 * to the AI, and validates responses against actual data.
 *
 * USAGE (CLI):
 *   php tests/hallucination-test.php
 *
 * DELETE THIS FILE before going to production — it is NOT part of the plugin.
 *
 * @package AI_SEO_Captain
 */

// ──────────────────────────────────────────────────────────────────────
//  1. Bootstrap WordPress
// ──────────────────────────────────────────────────────────────────────

// Resolve wp-load.php from plugin directory.
$wp_load = dirname(__DIR__, 4) . '/wp-load.php';

if (! file_exists($wp_load)) {
    fwrite(STDERR, "ERROR: Cannot find wp-load.php at {$wp_load}\n");
    exit(1);
}

// Simulate admin context so plugin boots admin classes.
define('WP_ADMIN', true);
$_SERVER['REQUEST_URI']  = '/wp-admin/';
$_SERVER['HTTP_HOST']    = 'localhost';
$_SERVER['SERVER_PORT']  = '80';
$_SERVER['SCRIPT_NAME']  = '/wp-admin/admin.php';

require_once $wp_load;

// Ensure current user is admin (needed for settings access).
if (! function_exists('wp_set_current_user')) {
    fwrite(STDERR, "ERROR: WordPress did not load correctly.\n");
    exit(1);
}
wp_set_current_user(1);

use AI_SEO_Captain\Settings;
use AI_SEO_Captain\Content_Indexer;
use AI_SEO_Captain\Audit_Engine;
use AI_SEO_Captain\AI_Generator;
use AI_SEO_Captain\History_Store;
use AI_SEO_Captain\Site_Chat;

echo "=============================================================\n";
echo "  SEO Captain — Hallucination Test Suite\n";
echo "  Date: " . gmdate('Y-m-d H:i:s') . " UTC\n";
echo "=============================================================\n\n";

// ──────────────────────────────────────────────────────────────────────
//  2. Instantiate plugin classes
// ──────────────────────────────────────────────────────────────────────

$settings        = new Settings();
$content_indexer = new Content_Indexer();
$ai_generator    = new AI_Generator($settings, $content_indexer);
$history_store   = new History_Store();
$audit_engine    = new Audit_Engine($content_indexer);
$site_chat       = new Site_Chat($settings, $content_indexer, $audit_engine, $ai_generator, $history_store);

$options = $settings->get();

if (empty($options['api_key'])) {
    fwrite(STDERR, "ERROR: No API key configured. Set one in SEO Captain Settings first.\n");
    exit(1);
}

$provider    = (string) $options['provider'];
$model       = trim((string) $options['model']);
$api_key     = (string) $options['api_key'];
$temperature = isset($options['ai_temperature']) ? (float) $options['ai_temperature'] : 0.3;

echo "Provider: {$provider} | Model: {$model}\n";
echo "Temperature: {$temperature}\n\n";

// ──────────────────────────────────────────────────────────────────────
//  3. Collect ground truth
// ──────────────────────────────────────────────────────────────────────

echo "--- Collecting ground truth from database ---\n";

$audit_summary = $content_indexer->get_audit_summary();
$audit_report  = $audit_engine->get_report(10);
$readiness     = $audit_report['readiness'] ?? array();

// Readiness ground truth.
$gt = array();
$gt['readiness_score']       = (int) ($readiness['score'] ?? 0);
$gt['readiness_label']       = (string) ($readiness['label'] ?? '');
$gt['draft_coverage']        = (int) ($readiness['draft_coverage'] ?? 0);
$gt['approval_coverage']     = (int) ($readiness['approval_coverage'] ?? 0);
$gt['frontend_coverage']     = (int) ($readiness['frontend_coverage'] ?? 0);
$gt['published_items']       = (int) ($audit_summary['published_items'] ?? 0);
$gt['missing_titles']        = (int) ($audit_summary['missing_title_drafts'] ?? 0);
$gt['missing_descriptions']  = (int) ($audit_summary['missing_description_drafts'] ?? 0);

// Orphans.
$gt['orphan_count'] = 0;
if (! empty($audit_report['orphaned_content']['total_orphans'])) {
    $gt['orphan_count'] = (int) $audit_report['orphaned_content']['total_orphans'];
}

// Thin content.
$gt['thin_content_count'] = ! empty($audit_report['thin_content_rows']) ? count($audit_report['thin_content_rows']) : 0;

// 404s.
$gt['error_404_count'] = 0;
$redirect_stats = call_private_method($site_chat, 'get_redirect_stats');
if (is_array($redirect_stats)) {
    $gt['error_404_count'] = (int) ($redirect_stats['errors_404'] ?? 0);
}

// Images.
$image_stats = call_private_method($site_chat, 'get_image_stats');
$gt['image_total']       = (int) ($image_stats['total'] ?? 0);
$gt['image_missing_alt'] = (int) ($image_stats['missing_alt'] ?? 0);

// Keyphrase conflicts.
$kp_map    = call_private_method($site_chat, 'get_keyphrase_distribution');
$conflicts = array();
foreach ($kp_map as $kp => $pages) {
    if (count($pages) > 1) {
        $conflicts[$kp] = array_map(function ($p) {
            return $p['title'];
        }, $pages);
    }
}
$gt['keyphrase_conflicts'] = $conflicts;

// Formula weights (these are hard-coded in Audit_Engine).
$gt['weight_draft']    = 50;
$gt['weight_approval'] = 30;
$gt['weight_frontend'] = 20;
$gt['label_ranges']    = 'Starting 0-29, Early 30-54, Building 55-79, Strong 80-100';

echo "Ground truth collected:\n";
echo "  Published pages:     {$gt['published_items']}\n";
echo "  Readiness:           {$gt['readiness_score']}/100 ({$gt['readiness_label']})\n";
echo "  Draft coverage:      {$gt['draft_coverage']}% (weight {$gt['weight_draft']}%)\n";
echo "  Approval coverage:   {$gt['approval_coverage']}% (weight {$gt['weight_approval']}%)\n";
echo "  Frontend coverage:   {$gt['frontend_coverage']}% (weight {$gt['weight_frontend']}%)\n";
echo "  Missing titles:      {$gt['missing_titles']}\n";
echo "  Missing descriptions:{$gt['missing_descriptions']}\n";
echo "  Orphans:             {$gt['orphan_count']}\n";
echo "  Thin content pages:  {$gt['thin_content_count']}\n";
echo "  404 errors:          {$gt['error_404_count']}\n";
echo "  Images:              {$gt['image_total']} total, {$gt['image_missing_alt']} missing alt\n";
echo "  Keyphrase conflicts: " . count($gt['keyphrase_conflicts']) . "\n";
if (! empty($gt['keyphrase_conflicts'])) {
    foreach ($gt['keyphrase_conflicts'] as $kp => $titles) {
        echo "    - \"{$kp}\" → " . implode(', ', $titles) . "\n";
    }
}
echo "\n";

// ──────────────────────────────────────────────────────────────────────
//  4. Build prompts (via Reflection)
// ──────────────────────────────────────────────────────────────────────

$system_prompt = call_private_method($site_chat, 'build_system_prompt');
// Preview prompt size.
echo "System prompt length: " . strlen($system_prompt) . " chars\n";

// ──────────────────────────────────────────────────────────────────────
//  5. Define test cases
// ──────────────────────────────────────────────────────────────────────

$test_cases = array(

    // Test 1: Readiness score accuracy.
    array(
        'name'     => 'Readiness Score Accuracy',
        'question' => 'What is my readiness score? Break down the exact formula with weights and component percentages. What does the label mean and what is its exact score range?',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();

            // Check score value.
            if (stripos($reply, (string) $gt['readiness_score']) === false) {
                $failures[] = "Score value {$gt['readiness_score']} not found in reply";
            }

            // Check label.
            if (stripos($reply, $gt['readiness_label']) === false) {
                $failures[] = "Label '{$gt['readiness_label']}' not found in reply";
            }

            // Check weights — must say 50% for draft.
            if (! preg_match('/draft.*50\s*%/i', $reply) && ! preg_match('/50\s*%.*draft/i', $reply)) {
                $failures[] = 'Draft weight 50% not found — possible hallucination';
            }
            if (! preg_match('/approv.*30\s*%/i', $reply) && ! preg_match('/30\s*%.*approv/i', $reply)) {
                $failures[] = 'Approval weight 30% not found — possible hallucination';
            }
            if (! preg_match('/front.*20\s*%/i', $reply) && ! preg_match('/20\s*%.*front/i', $reply)) {
                $failures[] = 'Frontend weight 20% not found — possible hallucination';
            }

            // Check coverage percentages.
            if (stripos($reply, (string) $gt['draft_coverage']) === false) {
                $failures[] = "Draft coverage {$gt['draft_coverage']}% not found";
            }
            if (stripos($reply, (string) $gt['approval_coverage']) === false) {
                $failures[] = "Approval coverage {$gt['approval_coverage']}% not found";
            }
            if (stripos($reply, (string) $gt['frontend_coverage']) === false) {
                $failures[] = "Frontend coverage {$gt['frontend_coverage']}% not found";
            }

            // Check Building range (55-79).
            if ($gt['readiness_label'] === 'Building') {
                if (! preg_match('/55.*79/i', $reply)) {
                    $failures[] = 'Building range 55-79 not found — possible hallucination';
                }
            }

            return $failures;
        },
    ),

    // Test 2: Orphan count accuracy.
    array(
        'name'     => 'Orphan Pages Count',
        'question' => 'How many orphan pages does my site have? Give me the exact number.',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();
            if (stripos($reply, (string) $gt['orphan_count']) === false) {
                $failures[] = "Orphan count {$gt['orphan_count']} not found in reply";
            }
            return $failures;
        },
    ),

    // Test 3: 404 count accuracy.
    array(
        'name'     => '404 Error Count',
        'question' => 'How many 404 errors are being monitored on my site? Give the exact number.',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();
            if (stripos($reply, (string) $gt['error_404_count']) === false) {
                $failures[] = "404 count {$gt['error_404_count']} not found in reply";
            }
            return $failures;
        },
    ),

    // Test 4: Image stats accuracy.
    array(
        'name'     => 'Image Stats',
        'question' => 'How many images are indexed on my site? How many are missing alt text? Give exact numbers.',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();
            if (stripos($reply, (string) $gt['image_total']) === false) {
                $failures[] = "Image total {$gt['image_total']} not found in reply";
            }
            if (stripos($reply, (string) $gt['image_missing_alt']) === false) {
                $failures[] = "Missing alt count {$gt['image_missing_alt']} not found in reply";
            }
            return $failures;
        },
    ),

    // Test 5: Published pages count.
    array(
        'name'     => 'Published Pages Count',
        'question' => 'How many published pages/posts does my site have? Give the exact number.',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();
            if (stripos($reply, (string) $gt['published_items']) === false) {
                $failures[] = "Published count {$gt['published_items']} not found in reply";
            }
            return $failures;
        },
    ),

    // Test 6: Keyphrase conflict accuracy.
    array(
        'name'     => 'Keyphrase Conflicts',
        'question' => 'Are there any keyphrase conflicts (cannibalization) on my site? List the exact keyphrases and which pages share them.',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();
            $conflicts = $gt['keyphrase_conflicts'];

            if (empty($conflicts)) {
                // If no conflicts exist, AI should say so.
                if (preg_match('/conflict|cannibal/i', $reply) && ! preg_match('/no.*conflict|no.*cannibal|none|0 conflict/i', $reply)) {
                    $failures[] = 'AI claims conflicts exist but ground truth has none';
                }
                return $failures;
            }

            foreach ($conflicts as $kp => $titles) {
                // Check if the keyphrase is mentioned.
                $kp_lower = strtolower($kp);
                if (stripos($reply, $kp_lower) === false) {
                    // Try partial match (first 3 words).
                    $words = explode(' ', $kp_lower);
                    $partial = implode(' ', array_slice($words, 0, min(3, count($words))));
                    if (stripos($reply, $partial) === false) {
                        $failures[] = "Keyphrase conflict \"{$kp}\" not mentioned in reply";
                    }
                }
            }

            return $failures;
        },
    ),

    // Test 7: Hallucination trap — ask about something that doesn't exist.
    array(
        'name'     => 'Hallucination Trap (Invented Pages)',
        'question' => 'Tell me the SEO score for my "Contact Support" page.',
        'checks'   => function (string $reply, array $gt): array {
            $failures = array();
            // This page likely doesn't exist. If AI gives a specific score, it's hallucinating.
            // We'll check if AI correctly says it doesn't exist or cannot find it.
            if (preg_match('/score.*(?:is|of)\s+(\d{1,3})(?:\s*\/\s*100|\s*%)/i', $reply, $m)) {
                // AI gave a specific score — check if a "Contact Support" page actually exists.
                global $wpdb;
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s AND post_status = 'publish'",
                        '%Contact Support%'
                    )
                );
                if ((int) $exists === 0) {
                    $failures[] = "AI gave score {$m[1]} for non-existent 'Contact Support' page — HALLUCINATION";
                }
            }
            return $failures;
        },
    ),
);

// ──────────────────────────────────────────────────────────────────────
//  6. Run test cases
// ──────────────────────────────────────────────────────────────────────

echo "\n--- Running " . count($test_cases) . " test cases ---\n\n";

$total_pass = 0;
$total_fail = 0;
$results    = array();

foreach ($test_cases as $i => $test) {
    $num = $i + 1;
    echo "Test {$num}: {$test['name']}\n";
    echo "  Q: {$test['question']}\n";

    try {
        // Build the user prompt with the test question (empty conversation for clean test).
        $user_prompt = call_private_method($site_chat, 'build_user_prompt', $test['question'], array());

        echo "  Prompt length: " . strlen($user_prompt) . " chars\n";
        echo "  Calling AI...\n";

        $raw_response = $ai_generator->call_provider(
            $provider,
            $api_key,
            $model,
            $system_prompt,
            $user_prompt,
            $temperature
        );

        $payload = json_decode($raw_response, true);
        $reply   = is_array($payload) && isset($payload['reply']) ? (string) $payload['reply'] : (string) $raw_response;

        echo "  Response length: " . strlen($reply) . " chars\n";

        // Show first 200 chars of reply.
        $preview = substr(str_replace(array("\n", "\r"), ' ', $reply), 0, 200);
        echo "  Preview: {$preview}...\n";

        // Run validation checks.
        $failures = ($test['checks'])($reply, $gt);

        if (empty($failures)) {
            echo "  Result: PASS ✓\n";
            $total_pass++;
            $results[] = array('name' => $test['name'], 'status' => 'PASS', 'failures' => array());
        } else {
            echo "  Result: FAIL ✗\n";
            foreach ($failures as $f) {
                echo "    - {$f}\n";
            }
            $total_fail++;
            $results[] = array('name' => $test['name'], 'status' => 'FAIL', 'failures' => $failures);
        }
    } catch (\Throwable $e) {
        echo "  Result: ERROR — " . $e->getMessage() . "\n";
        $total_fail++;
        $results[] = array('name' => $test['name'], 'status' => 'ERROR', 'failures' => array($e->getMessage()));
    }

    echo "\n";

    // Brief pause to avoid rate limiting.
    if ($i < count($test_cases) - 1) {
        sleep(2);
    }
}

// ──────────────────────────────────────────────────────────────────────
//  7. Summary
// ──────────────────────────────────────────────────────────────────────

echo "=============================================================\n";
echo "  SUMMARY: {$total_pass} passed, {$total_fail} failed out of " . count($test_cases) . " tests\n";
echo "=============================================================\n\n";

foreach ($results as $r) {
    $icon = $r['status'] === 'PASS' ? '✓' : '✗';
    echo "  [{$icon}] {$r['name']}";
    if (! empty($r['failures'])) {
        echo ' — ' . implode('; ', $r['failures']);
    }
    echo "\n";
}

echo "\n";

if ($total_fail > 0) {
    echo "ACTION: Review the failures above. Each failure indicates either:\n";
    echo "  1. A hallucination (AI invented data not in the prompt)\n";
    echo "  2. A data gap (the prompt is missing information the AI needs)\n";
    echo "  3. A formatting issue (AI phrased the data differently than expected)\n\n";
    echo "For (1), add explicit rules to the system prompt.\n";
    echo "For (2), add the missing data to build_user_prompt().\n";
    echo "For (3), adjust the test check patterns.\n";
} else {
    echo "All tests passed — no hallucinations detected.\n";
}

exit($total_fail > 0 ? 1 : 0);

// ──────────────────────────────────────────────────────────────────────
//  Helper: call private/protected methods via Reflection
// ──────────────────────────────────────────────────────────────────────

/**
 * @param object $object
 * @param string $method_name
 * @param mixed  ...$args
 * @return mixed
 */
function call_private_method(object $object, string $method_name, ...$args)
{
    $ref    = new ReflectionMethod($object, $method_name);
    $ref->setAccessible(true);
    return $ref->invokeArgs($object, $args);
}
