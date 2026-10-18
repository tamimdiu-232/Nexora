<?php

namespace Database\Seeders;

use App\Jobs\ProcessDocumentJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;

/**
 * DEVELOPMENT ONLY. All organizations, people and documents are synthetic.
 * Every demo account uses the password "Password@123" - never use these credentials in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(DocumentService $docs): void
    {
        $a = Organization::create(['name' => 'Demo Ministry of Infrastructure (Synthetic)', 'code' => 'demo-moi']);
        $b = Organization::create(['name' => 'Demo Local Government Authority (Synthetic)', 'code' => 'demo-lga']);
        $planning = Department::create(['organization_id' => $a->id, 'name' => 'Planning Division']);
        $finance = Department::create(['organization_id' => $a->id, 'name' => 'Finance Division']);
        $works = Department::create(['organization_id' => $b->id, 'name' => 'Works Department']);

        $mk = fn ($name, $email, $role, $org = null, $dept = null) => User::create([
            'name' => $name, 'email' => $email, 'password' => 'Password@123', 'role' => $role,
            'organization_id' => $org?->id, 'department_id' => $dept?->id, 'is_active' => true,
        ]);
        $mk('Demo Super Admin', 'super@govdoc.test', 'super_admin');
        $mk('Demo Org Admin (A)', 'orgadmin@demo-a.test', 'org_admin', $a);
        $mk('Demo Planning Dept Admin', 'deptadmin.planning@demo-a.test', 'dept_admin', $a, $planning);
        $officer = $mk('Demo Planning Officer', 'officer.planning@demo-a.test', 'officer', $a, $planning);
        $mk('Demo Reviewer (A)', 'reviewer@demo-a.test', 'reviewer', $a, $planning);
        $mk('Demo Finance Viewer', 'viewer.finance@demo-a.test', 'viewer', $a, $finance);
        $orgAdminB = $mk('Demo Org Admin (B)', 'orgadmin@demo-b.test', 'org_admin', $b);
        $mk('Demo Works Officer (B)', 'officer@demo-b.test', 'officer', $b, $works);

        $dir = base_path('../sample-documents');
        if (!is_dir($dir)) {
            $this->command?->warn("sample-documents folder not found at $dir; skipping demo documents.");
            return;
        }
        // [file, title, organization, department|null, uploader, category (null = let AI decide)]
        $items = [
            ['01_infrastructure_project_report.pdf', 'Demo Riverside Connector Project - Progress Report', $a, $planning, $officer],
            ['02_budget_report_fy2026_27.pdf', 'Budget Report FY 2026-27', $a, $finance, $officer],
            ['03_circular_digital_filing.pdf', 'Circular: Digital Filing of Progress Reports', $a, null, $officer],
            ['04_policy_procurement_documentation.pdf', 'Policy Guideline: Procurement Documentation', $a, null, $officer],
            ['05_meeting_minutes_planning_mar2026.pdf', 'Minutes: Planning Division Coordination (March 2026)', $a, $planning, $officer],
            ['06_revised_cost_estimate_drcp.pdf', 'Revised Cost Estimate - Riverside Connector (conflicting figures)', $a, $planning, $officer],
            ['08_scanned_notice.pdf', 'Scanned Official Notice (OCR demo)', $a, null, $officer],
            ['09_corrupted_sample.pdf', 'Corrupted file (failed-processing demo)', $a, $planning, $officer],
            ['07_orgB_road_maintenance_budget.pdf', 'Road Maintenance Budget (Organization B)', $b, null, $orgAdminB],
        ];
        foreach ($items as [$file, $title, $org, $dept, $uploader]) {
            if (!is_file("$dir/$file")) continue;
            $doc = $docs->store(new File("$dir/$file"), $uploader, $org->id, $dept?->id, ['title' => $title], $file);
            if (config('nexora.seed_process')) ProcessDocumentJob::dispatchSync($doc->id);
        }
    }
}
