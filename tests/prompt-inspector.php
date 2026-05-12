<?php

/**
 * Prompt Inspector — Dumps exact AI prompts for Copilot review (zero API cost)
 *
 * This script boots WordPress, builds the exact prompts that would be sent to
 * OpenAI/Google for any test question, and outputs them along with ground truth.
 * GitHub Copilot can then read the output and flag hallucination risks.
 *
 * USAGE (CLI):
 *   php tests/prompt-inspector.php
 *   php tests/prompt-inspector.php "your custom question here"
 *   php tests/prompt-inspector.php --page-chat 42
 *   php tests/prompt-inspector.php --json
 *
 * FLAGS:
 *   --json         Output structured JSON (for automated parsing)
 *   --page-chat N  Inspect the per-page chat prompt for post ID N
 *   (default)      Inspect the site-wide chat prompt
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │  DELETE THIS ENTIRE FILE before going to production.         │
 * │  It is NOT loaded by the plugin — 100% standalone.          │
 * └─────────────────────────────────────────────────────────────┘
 *
 * @package AI_SEO_Keeper
 */

// ──────────────────────────────────────────────────────────────────────
//  1. Bootstrap WordPress
// ──────────────────────────────────────────────────────────────────────

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';

if (! file_exists($wp_load)) {
    fwrite(STDERR, "ERROR: Cannot find wp-load.php at {$wp_load}\n");
    exit(1);
}

define('WP_ADMIN', true);
$_SERVER['REQUEST_URI']  = '/wp-admin/';
$_SERVER['HTTP_HOST']    = 'localhost';
$_SERVER['SERVER_PORT']  = '80';
$_SERVER['SCRIPT_NAME']  = '/wp-admin/admin.php';

require_once $wp_load;
wp_set_current_user(1);

use AI_SEO_Keeper\Settings;
use AI_SEO_Keeper\Content_Indexer;
use AI_SEO_Keeper\Audit_Engine;
use AI_SEO_Keeper\AI_Generator;
use AI_SEO_Keeper\History_Store;
use AI_SEO_Keeper\Site_Chat;

// ──────────────────────────────────────────────────────────────────────
//  2. Parse CLI arguments
// ──────────────────────────────────────────────────────────────────────

$args          = array_slice($argv ?? array(), 1);
$output_json   = in_array('--json', $args, true);
$page_chat_id  = null;
$custom_question = null;

foreach ($args as $i => $arg) {
    if ('--page-chat' === $arg && isset($args[$i + 1])) {
        $page_chat_id = (int) $args[$i + 1];
    }
    if ('--json' !== $arg && '--page-chat' !== $arg && (! isset($args[$i - 1]) || '--page-chat' !== $args[$i - 1])) {
        $custom_question = $arg;
    }
}

// ──────────────────────────────────────────────────────────────────────
//  3. Instantiate plugin classes
// ──────────────────────────────────────────────────────────────────────

$settings        = new Settings();
$content_indexer = new Content_Indexer();
$ai_generator    = new AI_Generator($settings, $content_indexer);
$history_store   = new History_Store();
$audit_engine    = new Audit_Engine($content_indexer);
$site_chat       = new Site_Chat($settings, $content_indexer, $audit_engine, $ai_generator, $history_store);

$options = $settings->get();

// ──────────────────────────────────────────────────────────────────────
//  4. Define test questions
// ──────────────────────────────────────────────────────────────────────

if ($custom_question) {
    $questions = array($custom_question);
} else {
    $questions = array(
        'What is my readiness score and what does it mean?',
        'Which pages need the most urgent SEO attention?',
        'Are there any keyphrase conflicts on my site?',
        'How many orphan pages do I have and which are the most important to fix?',
        'What is the overall SEO health of my site? Give me a complete assessment.',
    );
}

// ──────────────────────────────────────────────────────────────────────
//  5. Collect ground truth
// ──────────────────────────────────────────────────────────────────────

$audit_summary = $content_indexer->get_audit_summary();
$audit_report  = $audit_engine->get_report(10);
$readiness     = $audit_report['readiness'] ?? array();

$ground_truth = array(
    'site_name'            => get_bloginfo('name'),
    'site_url'             => home_url('/'),
    'published_items'      => (int) ($audit_summary['published_items'] ?? 0),
    'readiness_score'      => (int) ($readiness['score'] ?? 0),
    'readiness_label'      => (string) ($readiness['label'] ?? ''),
    'draft_coverage'       => (int) ($readiness['draft_coverage'] ?? 0),
    'approval_coverage'    => (int) ($readiness['approval_coverage'] ?? 0),
    'frontend_coverage'    => (int) ($readiness['frontend_coverage'] ?? 0),
    'formula_weights'      => 'draft×50% + approval×30% + frontend×20%',
    'label_ranges'         => 'Starting 0-29, Early 30-54, Building 55-79, Strong 80-100',
    'missing_titles'       => (int) ($audit_summary['missing_title_drafts'] ?? 0),
    'missing_descriptions' => (int) ($audit_summary['missing_description_drafts'] ?? 0),
    'orphan_count'         => (int) ($audit_report['orphaned_content']['total_orphans'] ?? 0),
    'thin_content_count'   => ! empty($audit_report['thin_content_rows']) ? count($audit_report['thin_content_rows']) : 0,
    'error_404_count'      => 0,
    'image_total'          => 0,
    'image_missing_alt'    => 0,
    'keyphrase_conflicts'  => array(),
);

// Private method helpers via Reflection.
$redirect_stats = call_private($site_chat, 'get_redirect_stats');
if (is_array($redirect_stats)) {
    $ground_truth['error_404_count'] = (int) ($redirect_stats['errors_404'] ?? 0);
}

$image_stats = call_private($site_chat, 'get_image_stats');
$ground_truth['image_total']       = (int) ($image_stats['total'] ?? 0);
$ground_truth['image_missing_alt'] = (int) ($image_stats['missing_alt'] ?? 0);

$kp_map = call_private($site_chat, 'get_keyphrase_distribution');
foreach ($kp_map as $kp => $pages) {
    if (count($pages) > 1) {
        $ground_truth['keyphrase_conflicts'][$kp] = array_map(function ($p) {
            return $p['title'];
        }, $pages);
    }
}

// ──────────────────────────────────────────────────────────────────────
//  6. Build and dump prompts
// ──────────────────────────────────────────────────────────────────────

$system_prompt = call_private($site_chat, 'build_system_prompt');

$inspections = array();

foreach ($questions as $question) {
    $user_prompt = call_private($site_chat, 'build_user_prompt', $question, array());

    $token_estimate = estimate_tokens($system_prompt . $user_prompt);

    $inspection = array(
        'question'         => $question,
        'system_prompt'    => $system_prompt,
        'user_prompt'      => $user_prompt,
        'prompt_chars'     => strlen($system_prompt) + strlen($user_prompt),
        'token_estimate'   => $token_estimate,
        'risk_analysis'    => analyze_risks($system_prompt, $user_prompt, $ground_truth),
    );

    $inspections[] = $inspection;
}

// ──────────────────────────────────────────────────────────────────────
//  7. Output
// ──────────────────────────────────────────────────────────────────────

$output = array(
    'generated_at'   => gmdate('Y-m-d H:i:s') . ' UTC',
    'provider'       => (string) ($options['provider'] ?? 'unknown'),
    'model'          => trim((string) ($options['model'] ?? 'unknown')),
    'ground_truth'   => $ground_truth,
    'inspections'    => $inspections,
);

if ($output_json) {
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

// ─── Human-readable output ───────────────────────────────────────────

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║          AI SEO Keeper — Prompt Inspector                    ║\n";
echo "║          " . $output['generated_at'] . "                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "Provider: {$output['provider']} | Model: {$output['model']}\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  GROUND TRUTH (actual DB values — AI answers must match these)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

foreach ($ground_truth as $key => $value) {
    if (is_array($value)) {
        echo "  {$key}:\n";
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                echo "    {$k} → " . implode(', ', $v) . "\n";
            } else {
                echo "    {$k} → {$v}\n";
            }
        }
    } else {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n";

foreach ($inspections as $i => $insp) {
    $num = $i + 1;
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  INSPECTION #{$num}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "  Question: {$insp['question']}\n";
    echo "  Total chars: {$insp['prompt_chars']} | ~{$insp['token_estimate']} tokens\n\n";

    // Risk analysis.
    if (! empty($insp['risk_analysis'])) {
        echo "  ⚠️  RISK ANALYSIS:\n";
        foreach ($insp['risk_analysis'] as $risk) {
            echo "    [{$risk['severity']}] {$risk['message']}\n";
        }
        echo "\n";
    } else {
        echo "  ✓ No structural risks detected.\n\n";
    }

    echo "  ┌─── SYSTEM PROMPT (" . strlen($insp['system_prompt']) . " chars) ───┐\n";
    echo indent_text($insp['system_prompt'], '  │ ') . "\n";
    echo "  └────────────────────────────────────────────┘\n\n";

    echo "  ┌─── USER PROMPT (" . strlen($insp['user_prompt']) . " chars) ───┐\n";
    echo indent_text($insp['user_prompt'], '  │ ') . "\n";
    echo "  └────────────────────────────────────────────┘\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  END OF INSPECTION — Copilot can now analyze above prompts\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

exit(0);

// ══════════════════════════════════════════════════════════════════════
//  Helper functions
// ══════════════════════════════════════════════════════════════════════

function call_private(object $obj, string $method, ...$args)
{
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($obj, $args);
}

/**
 * Rough token estimate (~4 chars per token for English).
 */
function estimate_tokens(string $text): int
{
    return (int) ceil(strlen($text) / 4);
}

/**
 * Automated risk analysis — flags structural issues in prompts.
 */
function analyze_risks(string $system, string $user, array $gt): array
{
    $risks = array();

    // 1. Check if formula weights are present.
    if (stripos($user, 'weight 50%') === false && stripos($user, '× 50') === false) {
        $risks[] = array(
            'severity' => 'HIGH',
            'message'  => 'Draft weight (50%) not explicitly stated — AI may guess wrong weights',
        );
    }

    // 2. Check if label ranges are present.
    if (stripos($user, '55') === false || stripos($user, '79') === false) {
        $risks[] = array(
            'severity' => 'HIGH',
            'message'  => 'Score label ranges not found — AI may hallucinate threshold values',
        );
    }

    // 3. Token budget warning.
    $total_tokens = estimate_tokens($system . $user);
    if ($total_tokens > 12000) {
        $risks[] = array(
            'severity' => 'MEDIUM',
            'message'  => "Prompt is ~{$total_tokens} tokens — approaching context limits for smaller models",
        );
    }
    if ($total_tokens > 28000) {
        $risks[] = array(
            'severity' => 'HIGH',
            'message'  => "Prompt is ~{$total_tokens} tokens — may exceed GPT-3.5/Gemini-Flash limits",
        );
    }

    // 4. Check for ambiguous data that could cause hallucination.
    if ($gt['image_total'] <= 1) {
        $risks[] = array(
            'severity' => 'LOW',
            'message'  => "Only {$gt['image_total']} image(s) indexed — method counts <img> in post_content only, may miss page-builder/featured images. AI might incorrectly say 'your site has almost no images'",
        );
    }

    // 5. Check if orphan count is in prompt.
    if ($gt['orphan_count'] > 0 && stripos($user, (string) $gt['orphan_count']) === false) {
        $risks[] = array(
            'severity' => 'HIGH',
            'message'  => "Orphan count ({$gt['orphan_count']}) not found in prompt text",
        );
    }

    // 6. Check if the prompt tells AI NOT to invent pages.
    if (stripos($system, 'Do not invent') === false && stripos($system, 'do not fabricate') === false) {
        $risks[] = array(
            'severity' => 'MEDIUM',
            'message'  => 'System prompt missing anti-hallucination rule (do not invent pages/data)',
        );
    }

    // 7. Check if the anti-guess rule for formulas is present.
    if (stripos($system, 'never guess') === false && stripos($system, 'exact numbers') === false) {
        $risks[] = array(
            'severity' => 'MEDIUM',
            'message'  => 'System prompt missing "never guess formulas" rule',
        );
    }

    // 8. Large orphan list might overwhelm response focus.
    if ($gt['orphan_count'] > 30) {
        $risks[] = array(
            'severity' => 'LOW',
            'message'  => "{$gt['orphan_count']} orphans listed — AI may summarize instead of listing all, or pick arbitrary subset",
        );
    }

    // 9. Check for conversation context leaking.
    if (preg_match_all('/Recent conversation:/i', $user, $m) > 0) {
        $risks[] = array(
            'severity' => 'LOW',
            'message'  => 'Conversation history included — prior messages may bias response to current question',
        );
    }

    // 10. Data freshness — no timestamp telling AI when data was collected.
    if (stripos($user, 'date') === false && stripos($user, 'as of') === false && stripos($user, gmdate('Y')) === false) {
        $risks[] = array(
            'severity' => 'LOW',
            'message'  => 'No data timestamp in prompt — AI cannot tell user how fresh the data is',
        );
    }

    return $risks;
}

/**
 * Indent text block with a prefix on each line.
 */
function indent_text(string $text, string $prefix): string
{
    $lines = explode("\n", $text);
    return implode("\n", array_map(function ($line) use ($prefix) {
        return $prefix . $line;
    }, $lines));
}
