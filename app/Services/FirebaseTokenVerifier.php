<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FirebaseTokenVerifier
{
    public function verify(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw ValidationException::withMessages(['email' => 'Firebase sign-in token is invalid.']);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        $projectId = config('services.firebase.project_id');
        $kid = $header['kid'] ?? null;

        if (($header['alg'] ?? null) !== 'RS256' || ! $kid || ! $projectId) {
            throw ValidationException::withMessages(['email' => 'Firebase authentication is not configured correctly.']);
        }

        $certificates = Cache::remember('firebase_securetoken_certificates', now()->addHours(6), function () {
            $response = Http::timeout(8)->get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');

            if (! $response->successful()) {
                throw ValidationException::withMessages(['email' => 'Unable to verify Firebase sign-in right now.']);
            }

            return $response->json();
        });

        $certificate = $certificates[$kid] ?? null;

        if (! $certificate || openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw ValidationException::withMessages(['email' => 'Firebase sign-in could not be verified.']);
        }

        $now = time();
        $issuer = 'https://securetoken.google.com/'.$projectId;

        if (($payload['aud'] ?? null) !== $projectId || ($payload['iss'] ?? null) !== $issuer || ($payload['exp'] ?? 0) < $now) {
            throw ValidationException::withMessages(['email' => 'Firebase sign-in has expired. Please sign in again.']);
        }

        if (empty($payload['sub']) || empty($payload['email'])) {
            throw ValidationException::withMessages(['email' => 'Firebase account is missing required profile details.']);
        }

        return $payload;
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($this->base64UrlDecode($value), true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['email' => 'Firebase sign-in token is malformed.']);
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4)) ?: '';
    }
}
