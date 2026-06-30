<?php

declare(strict_types=1);

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @return array{valid: bool, data: array<string, string>, errors: array<string, string>}
     */
    public function validateRegistration(array $data): array
    {
        $this->errors = [];

        $name = Security::sanitizeString((string) ($data['name'] ?? ''), 80);
        $mobile = preg_replace('/\D/', '', (string) ($data['mobile'] ?? '')) ?? '';
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $pincode = preg_replace('/\D/', '', (string) ($data['pincode'] ?? '')) ?? '';
        $state = Security::sanitizeString((string) ($data['state'] ?? ''), 60);
        $district = Security::sanitizeString((string) ($data['district'] ?? ''), 60);

        if ($name === '' || mb_strlen($name) < 2) {
            $this->errors['name'] = 'Please enter a valid full name (at least 2 characters).';
        }

        if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            $this->errors['mobile'] = 'Please enter a valid 10-digit Indian mobile number.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 255) {
            $this->errors['email'] = 'Email address is too long.';
        }

        if (!preg_match('/^[0-9]{6}$/', $pincode)) {
            $this->errors['pincode'] = 'Please enter a valid 6-digit pincode.';
        }

        if ($state === '' || mb_strlen($state) < 2) {
            $this->errors['state'] = 'Please enter a valid state.';
        }

        if ($district === '' || mb_strlen($district) < 2) {
            $this->errors['district'] = 'Please enter a valid district.';
        }

        return [
            'valid' => $this->errors === [],
            'data' => [
                'name' => $name,
                'mobile' => $mobile,
                'email' => $email,
                'pincode' => $pincode,
                'state' => $state,
                'district' => $district,
            ],
            'errors' => $this->errors,
        ];
    }
}
