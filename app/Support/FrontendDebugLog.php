<?php

namespace App\Support;

use Throwable;

final class FrontendDebugLog
{
    private const ALLOWED_FILES = [
        'newsletter' => 'debug_newsletter.log',
        'registrazione_cliente' => 'debug_registrazione_cliente.log',
        'autenticazione' => 'debug_autenticazione.log',
        'profilo_cliente' => 'debug_profilo_cliente.log',
        'contatto' => 'debug_contatto.log',
        'prodotto_navigazione' => 'debug_prodotto_navigazione.log',
        'preventivo' => 'debug_preventivo.log',
        'carrello_pagamento' => 'debug_carrello_pagamento.log',
        'prezzo_configuratore' => 'debug_prezzo_configuratore.log',
        'prezzo_carrello' => 'debug_prezzo_carrello.log',
    ];

    public static function newsletter(string $message, array $context = []): void
    {
        self::write('newsletter', $message, $context);
    }

    public static function registrazioneCliente(string $message, array $context = []): void
    {
        self::write('registrazione_cliente', $message, $context);
    }

    public static function autenticazione(string $message, array $context = []): void
    {
        self::write('autenticazione', $message, $context);
    }

    public static function profiloCliente(string $message, array $context = []): void
    {
        self::write('profilo_cliente', $message, $context);
    }

    public static function contatto(string $message, array $context = []): void
    {
        self::write('contatto', $message, $context);
    }

    public static function prodottoNavigazione(string $message, array $context = []): void
    {
        self::write('prodotto_navigazione', $message, $context);
    }

    public static function preventivo(string $message, array $context = []): void
    {
        self::write('preventivo', $message, $context);
    }

    public static function carrelloPagamento(string $message, array $context = []): void
    {
        self::write('carrello_pagamento', $message, $context);
    }

    public static function prezzoConfiguratore(string $message, array $context = []): void
    {
        self::write('prezzo_configuratore', $message, $context);
    }

    public static function prezzoCarrello(string $message, array $context = []): void
    {
        self::write('prezzo_carrello', $message, $context);
    }

    private static function write(string $slug, string $message, array $context): void
    {
        if (! config('app.extended_failsafe_logging', false)) {
            return;
        }

        try {
            if (! isset(self::ALLOWED_FILES[$slug])) {
                return;
            }

            $logDir = storage_path('logs');
            if (! is_dir($logDir) || ! is_writable($logDir)) {
                return;
            }

            $line = '['.date('Y-m-d H:i:s').'] '.self::toSafeString($message);
            $contextWithMeta = self::withDefaultMeta($context);
            if (! empty($contextWithMeta)) {
                $encoded = json_encode(
                    self::normalizeContext($contextWithMeta),
                    JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if ($encoded !== false) {
                    $line .= ' | '.$encoded;
                }
            }

            file_put_contents(
                $logDir.DIRECTORY_SEPARATOR.self::ALLOWED_FILES[$slug],
                $line.PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable $e) {
            CaughtExceptionLogger::error('FrontendDebugLog::write failed', $e, ['slug' => $slug]);

            return;
        }
    }

    private static function normalizeContext(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[$key] = self::normalizeValue($value);
        }

        return $normalized;
    }

    private static function withDefaultMeta(array $context): array
    {
        if (! array_key_exists('ip', $context)) {
            $context['ip'] = self::resolveClientIp();
        }

        $authenticatedUserMeta = self::resolveAuthenticatedUserMeta();
        foreach ($authenticatedUserMeta as $key => $value) {
            if (! array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }

        return $context;
    }

    private static function resolveClientIp(): ?string
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }

            $ip = request()->ip();
            if (! is_string($ip) || $ip === '') {
                return null;
            }

            return self::toSafeString($ip);
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function resolveAuthenticatedUserMeta(): array
    {
        try {
            if (! app()->bound('auth')) {
                return [];
            }

            if (! auth()->check()) {
                return [];
            }

            $user = auth()->user();
            if (! is_object($user)) {
                return [];
            }

            if (! isset($user->email) || ! is_string($user->email) || $user->email === '') {
                return [];
            }

            return [
                'user_email' => self::toSafeString($user->email),
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return self::toSafeString($value);
        }

        if (is_array($value)) {
            return self::normalizeContext($value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return self::toSafeString((string) $value);
        }

        return gettype($value);
    }

    private static function toSafeString(string $value): string
    {
        $singleLine = str_replace(["\r", "\n"], ' ', $value);

        return mb_substr($singleLine, 0, 2000);
    }
}
