<?php

declare(strict_types=1);

use App\Auth\Exceptions\OtpRejected;
use App\Auth\Exceptions\OtpThrottled;
use App\Auth\OtpService;
use App\Models\OtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    $this->otp = app(OtpService::class);
});

it('issues six-digit codes stored as hashes and invalidates the previous one', function (): void {
    ['otp' => $first, 'code' => $code1] = $this->otp->issue('A@Example.com', 'email', '10.0.0.1');
    ['otp' => $second, 'code' => $code2] = $this->otp->issue('a@example.com', 'email', '10.0.0.1');

    expect($code1)->toMatch('/^\d{6}$/')->and($code2)->toMatch('/^\d{6}$/')
        ->and($first->identifier)->toBe('a@example.com')
        ->and($first->fresh()->consumed_at)->not->toBeNull()
        ->and($second->code_hash)->not->toBe($code2)
        ->and($second->expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(10);

    expect(fn () => $this->otp->verify('a@example.com', $code1, '10.0.0.2'))->toThrow(OtpRejected::class);
    expect($this->otp->verify('a@example.com', $code2, '10.0.0.2')->id)->toBe($second->id);
});

it('counts attempts and limits sends per identifier and per IP', function (): void {
    ['code' => $code] = $this->otp->issue('b@example.com', 'email', '10.0.0.1');
    $wrong = $code === '123456' ? '654321' : '123456';
    try {
        $this->otp->verify('b@example.com', $wrong, null);
    } catch (OtpRejected $e) {
        expect($e->reason)->toBe('invalid')->and($e->attemptsLeft)->toBe(4);
    }

    $this->otp->issue('b@example.com', 'email', '10.0.0.1');
    $this->otp->issue('b@example.com', 'email', '10.0.0.1');
    expect(fn () => $this->otp->issue('b@example.com', 'email', '10.0.0.1'))->toThrow(OtpThrottled::class);

    for ($i = 0; $i < OtpService::SENDS_PER_IP; $i++) {
        $this->otp->issue("user{$i}@example.com", 'email', '10.9.9.9');
    }
    expect(fn () => $this->otp->issue('another@example.com', 'email', '10.9.9.9'))->toThrow(OtpThrottled::class);
});

it('consumes a magic-link id once and normalises digits', function (): void {
    ['otp' => $otp] = $this->otp->issue('c@example.com', 'email', null);
    expect($this->otp->consume($otp->id)?->id)->toBe($otp->id)
        ->and($this->otp->consume($otp->id))->toBeNull()
        ->and($this->otp->consume(424242))->toBeNull()
        ->and(OtpService::digits('١٢٣ 456'))->toBe('123456')
        ->and(OtpCode::query()->whereNull('consumed_at')->count())->toBe(0);
});
