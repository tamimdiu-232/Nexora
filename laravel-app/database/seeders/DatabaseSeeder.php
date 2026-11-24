<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Default development credentials password.
     * WARNING: FOR LOCAL SYNTHETIC DEMONSTRATION ONLY — NEVER USE IN PRODUCTION.
     */
    public const DEFAULT_DEV_PASSWORD = 'Password@123';

    public function run(): void
    {
        // 1. Guard against duplicate seeding
        if (User::count() > 0) {
            $this->command->info('Database already seeded. Skipping seeder to prevent duplicate key violations.');
            return;
        }

        $this->command->info('Seeding Nexora synthetic demonstration dataset...');
        $hashedPassword = Hash::make(self::DEFAULT_DEV_PASSWORD);

        // 2. Seed Organization A
        $orgA = Organization::create([
            'name' => 'Demo Ministry of Infrastructure (Synthetic)',
            'code' => 'MOI_SYNTHETIC',
            'is_active' => true,
        ]);

        $deptPlanning = Department::create([
            'organization_id' => $orgA->id,
            'name' => 'Planning Division',
            'is_active' => true,
        ]);

        $deptFinance = Department::create([
            'organization_id' => $orgA->id,
            'name' => 'Finance Division',
            'is_active' => true,
        ]);

        // 3. Seed Organization B
        $orgB = Organization::create([
            'name' => 'Demo Local Government Authority (Synthetic)',
            'code' => 'LGA_SYNTHETIC',
            'is_active' => true,
        ]);

        $deptWorks = Department::create([
            'organization_id' => $orgB->id,
            'name' => 'Works Department',
            'is_active' => true,
        ]);

        // 4. Seed Users
        // Platform Plane: Super Admin (no organization)
        User::create([
            'name' => 'Super Administrator',
            'email' => 'super@nexora.test',
            'password' => $hashedPassword,
            'role' => 'super_admin',
            'organization_id' => null,
            'department_id' => null,
            'is_active' => true,
        ]);

        // Org A Users
        User::create([
            'name' => 'Tariq Ahmed (Org Admin)',
            'email' => 'orgadmin@demo-a.test',
            'password' => $hashedPassword,
            'role' => 'org_admin',
            'organization_id' => $orgA->id,
            'department_id' => null,
            'is_active' => true,
        ]);

        $deptAdminPlanning = User::create([
            'name' => 'Farhana Kabir (Dept Admin)',
            'email' => 'deptadmin.planning@demo-a.test',
            'password' => $hashedPassword,
            'role' => 'dept_admin',
            'organization_id' => $orgA->id,
            'department_id' => $deptPlanning->id,
            'is_active' => true,
        ]);

        $officerPlanning = User::create([
            'name' => 'Rafiqul Islam (Officer)',
            'email' => 'officer.planning@demo-a.test',
            'password' => $hashedPassword,
            'role' => 'officer',
            'organization_id' => $orgA->id,
            'department_id' => $deptPlanning->id,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Dr. Nusrat Jahan (Reviewer)',
            'email' => 'reviewer@demo-a.test',
            'password' => $hashedPassword,
            'role' => 'reviewer',
            'organization_id' => $orgA->id,
            'department_id' => null,
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Kamal Hossain (Finance Viewer)',
            'email' => 'viewer.finance@demo-a.test',
            'password' => $hashedPassword,
            'role' => 'viewer',
            'organization_id' => $orgA->id,
            'department_id' => $deptFinance->id,
            'is_active' => true,
        ]);

        // Org B Users
        User::create([
            'name' => 'Mahmudul Hasan (Org B Admin)',
            'email' => 'orgadmin@demo-b.test',
            'password' => $hashedPassword,
            'role' => 'org_admin',
            'organization_id' => $orgB->id,
            'department_id' => null,
            'is_active' => true,
        ]);

        $officerOrgB = User::create([
            'name' => 'Selim Reza (Org B Officer)',
            'email' => 'officer@demo-b.test',
            'password' => $hashedPassword,
            'role' => 'officer',
            'organization_id' => $orgB->id,
            'department_id' => $deptWorks->id,
            'is_active' => true,
        ]);

        // 5. Seed Documents
        $sampleDocsDir = base_path('../sample-documents');
        if (!is_dir($sampleDocsDir)) {
            $sampleDocsDir = storage_path('sample-documents');
        }

        $documentConfigs = [
            [
                'file' => 'demo_riverside_connector_report.pdf',
                'title' => 'Demo Riverside Connector Project — Progress Report',
                'org_id' => $orgA->id,
                'dept_id' => $deptPlanning->id,
                'uploader_id' => $officerPlanning->id,
                'category' => 'Project Report',
                'tags' => ['riverside', 'connector', 'progress', 'infrastructure'],
            ],
            [
                'file' => 'demo_fy26_budget_report.pdf',
                'title' => 'Annual Budget Allocation Report (FY 2026-27)',
                'org_id' => $orgA->id,
                'dept_id' => $deptFinance->id,
                'uploader_id' => $deptAdminPlanning->id,
                'category' => 'Budget or Financial Document',
                'tags' => ['budget', 'fy26', 'riverside', 'allocation'],
            ],
            [
                'file' => 'circular_digital_filing.pdf',
                'title' => 'Official Circular: Digital Filing Protocol (পরিপত্র)',
                'org_id' => $orgA->id,
                'dept_id' => null, // Org-wide
                'uploader_id' => $deptAdminPlanning->id,
                'category' => 'Circular',
                'tags' => ['circular', 'digital', 'filing', 'পরিপত্র'],
            ],
            [
                'file' => 'procurement_policy_guideline.pdf',
                'title' => 'Standard Operating Procedure: Procurement Policy Guidelines',
                'org_id' => $orgA->id,
                'dept_id' => null, // Org-wide
                'uploader_id' => $deptAdminPlanning->id,
                'category' => 'Policy or Guideline',
                'tags' => ['procurement', 'policy', 'sop', 'tenders'],
            ],
            [
                'file' => 'minutes_steering_committee_14.pdf',
                'title' => 'Minutes of the 14th Project Steering Committee Meeting',
                'org_id' => $orgA->id,
                'dept_id' => $deptPlanning->id,
                'uploader_id' => $officerPlanning->id,
                'category' => 'Meeting Minutes',
                'tags' => ['minutes', 'committee', 'meeting', 'steering'],
            ],
            [
                'file' => 'revised_cost_estimate_riverside.pdf',
                'title' => 'Revised Administrative Cost Estimate — Riverside Connector',
                'org_id' => $orgA->id,
                'dept_id' => $deptPlanning->id,
                'uploader_id' => $officerPlanning->id,
                'category' => 'Budget or Financial Document',
                'tags' => ['riverside', 'revised', 'budget', 'cost escalation'],
            ],
            [
                'file' => 'org_b_municipal_road_budget.pdf',
                'title' => 'Municipal Maintenance Allocation (Org B Isolated)',
                'org_id' => $orgB->id,
                'dept_id' => $deptWorks->id,
                'uploader_id' => $officerOrgB->id,
                'category' => 'Budget or Financial Document',
                'tags' => ['municipal', 'roads', 'local government'],
            ],
            [
                'file' => 'scanned_official_notice.pdf',
                'title' => 'Scanned Official Notice: Contractor Registration (OCR Case)',
                'org_id' => $orgA->id,
                'dept_id' => $deptPlanning->id,
                'uploader_id' => $officerPlanning->id,
                'category' => 'Official Notice',
                'tags' => ['scanned', 'ocr', 'contractors', 'notice'],
            ],
            [
                'file' => 'corrupt_sample.pdf',
                'title' => 'Corrupted Report Sample (Failure Recovery Test)',
                'org_id' => $orgA->id,
                'dept_id' => $deptPlanning->id,
                'uploader_id' => $officerPlanning->id,
                'category' => 'Other',
                'tags' => ['corrupt', 'test', 'recovery'],
            ],
            [
                'file' => 'scanned_bangla_notice.pdf',
                'title' => 'জরুরি অফিস বিজ্ঞপ্তি: ডেমো রিভারসাইড কানেক্টর প্রকল্প (Bangla OCR)',
                'org_id' => $orgA->id,
                'dept_id' => $deptPlanning->id,
                'uploader_id' => $officerPlanning->id,
                'category' => 'Official Notice',
                'tags' => ['বিজ্ঞপ্তি', 'বাংলা', 'বাজেট', 'সময়সীমা'],
            ],
        ];

        $shouldProcess = filter_var(env('NEXORA_SEED_PROCESS', false), FILTER_VALIDATE_BOOLEAN);
        $processor = app(DocumentProcessor::class);

        foreach ($documentConfigs as $cfg) {
            $srcPath = $sampleDocsDir . '/' . $cfg['file'];
            if (!file_exists($srcPath)) {
                $this->command->warn("Sample file {$cfg['file']} not found. Skipping document seed.");
                continue;
            }

            $uuid = (string) Str::uuid();
            $storedFilename = "documents/{$uuid}.pdf";
            $rawBytes = file_get_contents($srcPath);

            Storage::disk('private')->put($storedFilename, $rawBytes);

            $doc = Document::create([
                'uuid' => $uuid,
                'organization_id' => $cfg['org_id'],
                'department_id' => $cfg['dept_id'],
                'uploaded_by' => $cfg['uploader_id'],
                'title' => $cfg['title'],
                'description' => 'Synthetic demonstration document created by DatabaseSeeder.',
                'category' => $cfg['category'],
                'category_confidence' => 1.0,
                'category_source' => 'manual',
                'tags' => $cfg['tags'],
                'original_name' => $cfg['file'],
                'stored_path' => $storedFilename,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($rawBytes),
                'sha256' => hash('sha256', $rawBytes),
                'processing_status' => 'pending',
            ]);

            if ($shouldProcess) {
                $this->command->line("Processing {$doc->title}...");
                $processor->process($doc);
            }
        }

        $this->command->info('Seeding finished successfully.');
    }
}
