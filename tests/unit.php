<?php
declare(strict_types=1);

/**
 * Pure-function unit tests. No database or web server required.
 *
 * Run: php tests/unit.php
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Updater.php';

$failures = 0;

function check(string $label, $expected, $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "ok   $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n     expected: " . var_export($expected, true) . "\n     actual:   " . var_export($actual, true) . "\n";
}

// clean_string
check('clean_string trims whitespace', 'abc', clean_string("  abc  ", 10));
check('clean_string strips control chars', 'abc', clean_string("a\x00b\x1Fc", 10));
check('clean_string truncates to max length', 'abcde', clean_string('abcdefgh', 5));
check('clean_string keeps unicode text', 'Tiny Märker', clean_string('Tiny Märker', 40));

// clean_install_name
check('clean_install_name lowercases and filters', 'my-anim_2', clean_install_name('My-Anim_2!'));
check('clean_install_name falls back when empty', 'downloaded', clean_install_name('###'));
check('clean_install_name truncates to 40 chars', str_repeat('a', 40), clean_install_name(str_repeat('a', 60)));

// reserved boot animation names
check('reserved name default', true, boot_animation_install_name_reserved('Default'));
check('reserved name shuffle padded', true, boot_animation_install_name_reserved(' shuffle '));
check('non-reserved name', false, boot_animation_install_name_reserved('rocket'));

// tokens and ids
check('public_id is 16 hex chars', 1, preg_match('/^[a-f0-9]{16}$/', public_id()));
check('token is 64 hex chars', 1, preg_match('/^[a-f0-9]{64}$/', token()));

// html escaping
check('h escapes html', '&lt;b&gt;&quot;x&quot;&lt;/b&gt;', h('<b>"x"</b>'));
check('h handles null', '', h(null));

// request_is_https
$_SERVER['HTTPS'] = 'on';
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
check('request_is_https via HTTPS var', true, request_is_https());
$_SERVER['HTTPS'] = 'off';
check('request_is_https off', false, request_is_https());
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
check('request_is_https via forwarded proto', true, request_is_https());
unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

// updater version handling
check('normalize strips v prefix', '1.2.3', updater_normalize_version('v1.2.3'));
check('normalize keeps plain version', '1.2.3', updater_normalize_version('1.2.3'));
check('compare newer', 1, updater_compare_versions('v0.3.0', '0.2.2'));
check('compare equal', 0, updater_compare_versions('v0.2.2', '0.2.2'));
check('compare older', -1, updater_compare_versions('0.2.1', 'v0.2.2'));

// updater skip rules: these paths must never be overwritten by self-update
check('updater skips config', true, updater_is_skipped_path('app/config.php'));
check('updater skips storage', true, updater_is_skipped_path('storage/models/x.zip'));
check('updater skips storage root', true, updater_is_skipped_path('storage'));
check('updater skips .git', true, updater_is_skipped_path('.git/config'));
check('updater skips parent traversal', true, updater_is_skipped_path('app/../../evil.php'));
check('updater skips leading traversal', true, updater_is_skipped_path('../evil.php'));
check('updater skips directories', true, updater_is_skipped_path('app/'));
check('updater allows index.php', false, updater_is_skipped_path('index.php'));
check('updater allows app code', false, updater_is_skipped_path('app/Api.php'));
check('updater allows htaccess', false, updater_is_skipped_path('.htaccess'));

if ($failures > 0) {
    echo "\n$failures test(s) failed.\n";
    exit(1);
}
echo "\nAll unit tests passed.\n";
