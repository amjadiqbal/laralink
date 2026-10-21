<?php

use AmjadIqbal\Laralink\Support\CompatibilityChecker;

it('checks PHP and Laravel constraints with Composer semantics', function () {
    $checker = new CompatibilityChecker('8.4.5', '13.2.0');

    expect(array_column($checker->check(['require' => ['php' => '^8.3', 'illuminate/support' => '^12.0|^13.0']]), 'ok'))->toBe([true, true])
        ->and(array_column($checker->check(['require' => ['php' => '>=8.5', 'laravel/framework' => '^11.0']]), 'ok'))->toBe([false, false])
        ->and(array_column($checker->check(['require' => ['php' => '~8.4.0']]), 'ok'))->toBe([true, true]);
});

it('passes when the package states no constraint', function () {
    $result = (new CompatibilityChecker('8.3.0', '12.0.0'))->check([]);

    expect($result[0])->toMatchArray(['constraint' => null, 'ok' => true])
        ->and($result[1])->toMatchArray(['constraint' => null, 'ok' => true]);
});

it('handles PHP version strings with extra suffixes', function () {
    expect((new CompatibilityChecker('8.5.0-dev', '13.0.0'))->check(['require' => ['php' => '^8.5']])[0]['ok'])->toBeTrue();
});
