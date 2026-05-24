<?php

/**
 * CLI test runner for Step 2 (metadata) and Step 3 (page audit).
 *
 * Usage:
 *   php test-steps.php                       # Test Step 2 + 3 on the 4-page TT1 list
 *   php test-steps.php --step=2              # Step 2 only
 *   php test-steps.php --step=3              # Step 3 only
 *   php test-steps.php --deep                 # Step 2 with deep analysis (sibling content)
 *   php test-steps.php --pages=1545,17       # Specific page IDs
 *   php test-steps.php --pages=all --step=2  # All 43 pages, Step 2
 *   php test-steps.php --dry                 # Dry run: show prompts/tokens without calling AI
 *
 * This script calls the EXACT same code paths as the browser (generate_for_post,
 * generate_page_audit) so it's a true end-to-end test.
 */

// ── Bootstrap ────────────────────────────────────────────────────────
define('ABSPATH', dirname(__DIR__, 3) . '/');
require_once ABSPATH . 'wp-load.php';

// Load Local AI module files (normally loaded in is_admin context).
$local_ai_dir = __DIR__ . '/modules/local-ai/';
if (is_dir($local_ai_dir)) {
    require_once $local_ai_dir . 'class-local-ai-provider.php';
    require_once $local_ai_dir . 'class-local-ai-content-compressor.php';
}

use AI_SEO_Captain\Settings;
use AI_SEO_Captain\Content_Indexer;
use AI_SEO_Captain\AI_Generator;

// ── Parse CLI args ───────────────────────────────────────────────────
$args = array();
foreach ($argv as $arg) {
    if (preg_match('/^--(\w+)(?:=(.+))?$/', $arg, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}

$step     = isset($args['step']) ? (int) $args['step'] : 0; // 0 = both
$dry_run  = isset($args['dry']);
$deep     = isset($args['deep']);
$page_ids = array();

if (isset($args['pages'])) {
    if ('all' === $args['pages']) {
        $all = get_posts(array(
            'post_type'      => get_post_types(array('public' => true)),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        $page_ids = $all;
    } else {
        $page_ids = array_map('intval', explode(',', $args['pages']));
    }
} else {
    // Default: the TT1 list from the user's screenshot (4 pages that failed).
    $tt1_titles = array(
        'RPA Training - Automate with Expert Guidance',
        'Community: Collaborate and Innovate',
        'Automation Software Products | Marketplace',
        'AISG1: AI Software Generator',
    );
    foreach ($tt1_titles as $title) {
        $found = get_posts(array(
            'post_type'      => get_post_types(array('public' => true)),
            'post_status'    => 'publish',
            'title'          => $title,
            'posts_per_page' => 1,
        ));
        if (!empty($found)) {
            $page_ids[] = $found[0]->ID;
        } else {
            // Fallback: search.
            $found = get_posts(array(
                'post_type'      => get_post_types(array('public' => true)),
                'post_status'    => 'publish',
                's'              => $title,
                'posts_per_page' => 1,
            ));
            if (!empty($found)) {
                $page_ids[] = $found[0]->ID;
            } else {
                echo "⚠ Page not found: {$title}\n";
            }
        }
    }
}

if (empty($page_ids)) {
    echo "No pages to test.\n";
    exit(1);
}

// ── Setup ────────────────────────────────────────────────────────────
$settings        = new Settings();
$content_indexer = new Content_Indexer($settings);
$generator       = new AI_Generator($settings, $content_indexer);
$opts            = $settings->get();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  SEO Captain — CLI Step Test Runner                        ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Provider: " . str_pad($opts['provider'] ?? 'NOT SET', 47) . "║\n";
echo "║  Model:    " . str_pad($opts['local_model'] ?? ($opts['model'] ?? 'NOT SET'), 47) . "║\n";
echo "║  Context:  " . str_pad(number_format((int) ($opts['context_window'] ?? 128000)) . ' tokens', 47) . "║\n";
echo "║  Pages:    " . str_pad(count($page_ids) . ' pages', 47) . "║\n";
echo "║  Steps:    " . str_pad($step === 0 ? '2 + 3' : (string) $step, 47) . "║\n";
echo "║  Deep:     " . str_pad($deep ? 'YES (sibling content)' : 'no', 47) . "║\n";
echo "║  Mode:     " . str_pad($dry_run ? 'DRY RUN (no AI calls)' : 'LIVE', 47) . "║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ── Dry run: show prompt sizes ───────────────────────────────────────
if ($dry_run) {
    $ref = new ReflectionClass($generator);

    $buildSystem = $ref->getMethod('build_system_prompt');
    $buildSystem->setAccessible(true);

    $buildUser = $ref->getMethod('build_user_prompt');
    $buildUser->setAccessible(true);

    $buildAuditSystem = $ref->getMethod('build_page_audit_system_prompt');
    $buildAuditSystem->setAccessible(true);

    $buildAuditUser = $ref->getMethod('build_page_audit_user_prompt');
    $buildAuditUser->setAccessible(true);

    $ctx_window = (int) ($opts['context_window'] ?? 32768);
    $input_budget = (int) ($ctx_window * 0.6);

    foreach ($page_ids as $pid) {
        $post = get_post($pid);
        if (!$post) {
            echo "✗ ID {$pid}: post not found\n";
            continue;
        }

        echo "─── {$post->post_title} (ID: {$pid}) ───\n";

        if ($step === 0 || $step === 2) {
            $sp = $buildSystem->invoke($generator, (string) ($opts['system_prompt'] ?? ''));
            $overrides = $deep ? array('deep_analysis' => true) : array();
            $up = $buildUser->invoke($generator, $post, $overrides);
            $total = mb_strlen($sp) + mb_strlen($up);
            $est_tokens = (int) ceil($total / 3.5);
            $label = $deep ? 'Step 2 (deep)' : 'Step 2 (metadata)';
            $status = $est_tokens <= $input_budget ? '✅' : '⚠️ needs compression';
            echo "  {$label}: {$total} chars ≈ {$est_tokens} tokens {$status}\n";
        }

        if ($step === 0 || $step === 3) {
            $sp3 = $buildAuditSystem->invoke($generator, (string) ($opts['system_prompt'] ?? ''));
            $up3 = $buildAuditUser->invoke($generator, $post, false);
            $total3 = mb_strlen($sp3) + mb_strlen($up3);
            $est_tokens3 = (int) ceil($total3 / 3.5);
            $status3 = $est_tokens3 <= $input_budget ? '✅' : '⚠️ needs compression';
            echo "  Step 3 (audit):    {$total3} chars ≈ {$est_tokens3} tokens {$status3}\n";
        }
        echo "\n";
    }
    echo "Done (dry run — no AI calls made).\n";
    exit(0);
}

// ── Live test ────────────────────────────────────────────────────────
set_time_limit(0);
$results = array('step2' => array(), 'step3' => array());
$start_all = microtime(true);

foreach ($page_ids as $idx => $pid) {
    $post = get_post($pid);
    if (!$post) {
        echo "✗ ID {$pid}: post not found\n";
        continue;
    }

    $n = $idx + 1;
    $total = count($page_ids);

    // ── Step 2: Metadata ─────────────────────────────────────────────
    if ($step === 0 || $step === 2) {
        $label = $deep ? 'Step 2 (deep)' : 'Step 2';
        echo "[{$n}/{$total}] {$label} — {$post->post_title} ... ";
        $t = microtime(true);
        try {
            $overrides = $deep ? array('deep_analysis' => true) : array();
            $result = $generator->generate_for_post($pid, $overrides);
            $elapsed = round(microtime(true) - $t, 1);
            echo "✅ {$elapsed}s — \"{$result['seo_title']}\"\n";
            $results['step2'][] = array('id' => $pid, 'title' => $post->post_title, 'ok' => true, 'time' => $elapsed);
        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $t, 1);
            echo "✗ {$elapsed}s — {$e->getMessage()}\n";
            $results['step2'][] = array('id' => $pid, 'title' => $post->post_title, 'ok' => false, 'error' => $e->getMessage(), 'time' => $elapsed);
        }
    }

    // ── Step 3: Page Audit ───────────────────────────────────────────
    if ($step === 0 || $step === 3) {
        echo "[{$n}/{$total}] Step 3 — {$post->post_title} ... ";
        $t = microtime(true);
        try {
            $result = $generator->generate_page_audit($pid);
            $elapsed = round(microtime(true) - $t, 1);
            $score = $result['score'] ?? '?';
            echo "✅ {$elapsed}s — score: {$score}/100\n";
            $results['step3'][] = array('id' => $pid, 'title' => $post->post_title, 'ok' => true, 'score' => $score, 'time' => $elapsed);
        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $t, 1);
            echo "✗ {$elapsed}s — {$e->getMessage()}\n";
            $results['step3'][] = array('id' => $pid, 'title' => $post->post_title, 'ok' => false, 'error' => $e->getMessage(), 'time' => $elapsed);
        }
    }
}

// ── Summary ──────────────────────────────────────────────────────────
$total_time = round(microtime(true) - $start_all, 1);
echo "\n══════════════════════════════════════════════════════════════\n";
echo "  RESULTS — {$total_time}s total\n";
echo "══════════════════════════════════════════════════════════════\n";

foreach (array('step2' => 'Step 2 (Metadata)', 'step3' => 'Step 3 (Audit)') as $key => $label) {
    if (empty($results[$key])) continue;
    $ok = count(array_filter($results[$key], fn($r) => $r['ok']));
    $fail = count($results[$key]) - $ok;
    echo "\n  {$label}: {$ok} passed, {$fail} failed\n";
    foreach ($results[$key] as $r) {
        if ($r['ok']) {
            $extra = $key === 'step3' ? " (score: {$r['score']})" : '';
            echo "    ✅ {$r['title']} — {$r['time']}s{$extra}\n";
        } else {
            echo "    ✗ {$r['title']} — {$r['error']}\n";
        }
    }
}

echo "\n";
