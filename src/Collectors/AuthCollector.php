<?php

namespace Doppar\Insight\Collectors;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Support\Facades\Auth;
use Doppar\Insight\Contracts\CollectorInterface;

class AuthCollector implements CollectorInterface
{
    protected array $data = [];

    public function name(): string
    {
        return 'auth';
    }

    public function start(Request $request): void
    {
        $this->data = [
            'authenticated' => false,
            'user' => null,
            'user_id' => null,
            'user_name' => null,
            'user_email' => null,
            'guard' => null,
        ];

        // Check if user is authenticated using Auth facade
        try {
            if (Auth::check()) {
                $user = Auth::user();
                
                if ($user) {
                    $this->data['authenticated'] = true;
                    $this->data['user_id'] = $user->id ?? null;
                    $this->data['user_name'] = $user->name ?? null;
                    $this->data['user_email'] = $user->email ?? null;
                    
                    // Store safe user data (excluding sensitive fields)
                    $userData = is_object($user) && method_exists($user, 'toArray') ? $user->toArray() : (array)$user;
                    unset($userData['password'], $userData['remember_token'], $userData['two_factor_secret'], $userData['two_factor_recovery_codes']);
                    $this->data['user'] = $userData;
                }
            }
        } catch (\Exception $e) {
            // Silently fail if Auth facade is not available or throws an error
        }
    }

    public function stop(Request $request, Response $response): void
    {
        // Nothing to do on stop for auth collector
    }

    public function toArray(): array
    {
        return [
            'auth_authenticated' => $this->data['authenticated'],
            'auth_user_id' => $this->data['user_id'],
            'auth_user_name' => $this->data['user_name'],
            'auth_user_email' => $this->data['user_email'],
            'auth_user' => $this->data['user'],
            'auth_guard' => $this->data['guard'],
        ];
    }
}
