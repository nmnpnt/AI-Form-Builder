<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\Submission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create(['name' => 'Demo Org', 'slug' => 'demo-org']);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $form = Form::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'title' => 'Internship Application',
            'description' => 'Sample seeded form demonstrating most field types.',
            'status' => 'published',
            'source' => 'manual',
            'slug' => 'internship-application-demo',
        ]);

        $form->publishVersion([
            'sections' => [
                [
                    'key' => 'personal_info',
                    'title' => 'Personal Information',
                    'type' => 'section',
                    'fields' => [
                        $this->field('full_name', 'text', 'Full name', required: true),
                        $this->field('email', 'email', 'Email address', required: true),
                        $this->field('phone', 'phone', 'Phone number', required: false),
                        $this->field('dob', 'date', 'Date of birth', required: false),
                    ],
                ],
                [
                    'key' => 'education',
                    'title' => 'Education & Skills',
                    'type' => 'section',
                    'fields' => [
                        $this->field('degree', 'dropdown', 'Highest degree', required: true, options: [
                            ['value' => 'high_school', 'label' => 'High School'],
                            ['value' => 'bachelors', 'label' => "Bachelor's"],
                            ['value' => 'masters', 'label' => "Master's"],
                        ]),
                        $this->field('skills', 'checkbox', 'Relevant skills', required: false, options: [
                            ['value' => 'php', 'label' => 'PHP'],
                            ['value' => 'js', 'label' => 'JavaScript'],
                            ['value' => 'sql', 'label' => 'SQL'],
                        ]),
                        $this->field('cover_letter', 'textarea', 'Why do you want this role?', required: false),
                        $this->field('resume', 'file', 'Resume upload', required: true, validation: [
                            'file_types' => ['pdf', 'doc', 'docx'], 'max_size_kb' => 5120,
                        ]),
                        $this->field('confidence', 'rating', 'How confident are you in your skills?', required: false),
                    ],
                ],
            ],
        ], via: 'manual', userId: $user->id, summary: 'Initial seed');

        Submission::create([
            'form_id' => $form->id,
            'form_version_id' => $form->current_version_id,
            'payload' => [
                'full_name' => 'Asha Verma',
                'email' => 'asha@example.com',
                'phone' => '+91 98765 43210',
                'degree' => 'bachelors',
                'skills' => ['php', 'sql'],
                'cover_letter' => 'I am excited about this opportunity...',
                'confidence' => 4,
            ],
            'submitter_ip' => '127.0.0.1',
            'submitter_email' => 'asha@example.com',
        ]);
    }

    private function field(
        string $key,
        string $type,
        string $label,
        bool $required = false,
        ?array $options = null,
        array $validation = []
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'placeholder' => null,
            'help_text' => null,
            'default' => null,
            'required' => $required,
            'options' => $options,
            'validation' => $validation,
        ];
    }
}
