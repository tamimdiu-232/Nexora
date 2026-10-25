<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    public function test_viewer_cannot_upload_or_delete(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        $viewer = $this->user('viewer', $org, $d);
        $doc = $this->doc($org, $d);
        $this->actingAs($viewer)->get('/documents/create')->assertForbidden();
        $this->actingAs($viewer)->post('/documents', ['title' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->delete('/documents/' . $doc->uuid)->assertForbidden();
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'deleted_at' => null]);
    }

    public function test_other_department_document_is_forbidden_everywhere(): void
    {
        $org = $this->org(); $d1 = $this->dept($org); $d2 = $this->dept($org);
        $officer = $this->user('officer', $org, $d1);
        $doc = $this->doc($org, $d2);
        $this->actingAs($officer);
        $this->get('/documents/' . $doc->uuid)->assertForbidden();
        $this->get('/documents/' . $doc->uuid . '/preview')->assertForbidden();
        $this->get('/documents/' . $doc->uuid . '/download')->assertForbidden();
        $this->get('/documents/' . $doc->uuid . '/related')->assertForbidden();
        $this->post('/documents/' . $doc->uuid . '/summarize')->assertForbidden();
        $this->get('/documents/' . $doc->uuid . '/edit')->assertForbidden();
    }

    public function test_other_organization_document_is_forbidden_and_unknown_uuid_is_404(): void
    {
        $a = $this->org(); $b = $this->org();
        $user = $this->user('org_admin', $a);
        $doc = $this->doc($b);
        $this->actingAs($user)->get('/documents/' . $doc->uuid . '/download')->assertForbidden();
        $this->get('/documents/00000000-0000-0000-0000-000000000000/download')->assertNotFound();
        $this->get('/documents/1')->assertNotFound(); // sequential ids do not resolve
    }

    public function test_document_list_only_shows_visible_documents(): void
    {
        $a = $this->org(); $b = $this->org(); $d1 = $this->dept($a); $d2 = $this->dept($a);
        $mine = $this->doc($a, $d1, ['title' => 'VisibleOne']);
        $orgWide = $this->doc($a, null, ['title' => 'OrgWideDoc']);
        $hiddenDept = $this->doc($a, $d2, ['title' => 'HiddenDeptDoc']);
        $hiddenOrg = $this->doc($b, null, ['title' => 'HiddenOrgDoc']);
        $this->actingAs($this->user('viewer', $a, $d1))->get('/documents')
            ->assertSee('VisibleOne')->assertSee('OrgWideDoc')->assertDontSee('HiddenDeptDoc')->assertDontSee('HiddenOrgDoc');
        $this->actingAs($this->user('org_admin', $a))->get('/documents')->assertSee('HiddenDeptDoc')->assertDontSee('HiddenOrgDoc');
    }

    public function test_officer_can_only_modify_own_uploads(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        $o1 = $this->user('officer', $org, $d); $o2 = $this->user('officer', $org, $d);
        $theirs = $this->doc($org, $d, ['uploaded_by' => $o2->id]);
        $own = $this->doc($org, $d, ['uploaded_by' => $o1->id]);
        $this->actingAs($o1)->get('/documents/' . $theirs->uuid . '/edit')->assertForbidden();
        $this->put('/documents/' . $theirs->uuid, ['title' => 'Hacked', 'category' => 'Other'])->assertForbidden();
        $this->get('/documents/' . $own->uuid . '/edit')->assertOk();
    }

    public function test_officer_cannot_move_document_between_departments(): void
    {
        $org = $this->org(); $d1 = $this->dept($org); $d2 = $this->dept($org);
        $o = $this->user('officer', $org, $d1);
        $doc = $this->doc($org, $d1, ['uploaded_by' => $o->id]);
        $this->actingAs($o)->put('/documents/' . $doc->uuid, ['title' => 'T', 'category' => 'Other', 'department_id' => $d2->id])->assertRedirect();
        $this->assertSame($d1->id, $doc->fresh()->department_id);
    }

    public function test_category_correction_is_kept_as_manual(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        $admin = $this->user('dept_admin', $org, $d);
        $doc = $this->doc($org, $d);
        $this->actingAs($admin)->put('/documents/' . $doc->uuid, ['title' => $doc->title, 'category' => 'Circular'])->assertRedirect();
        $this->assertSame('manual', $doc->fresh()->category_source);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.updated', 'subject_id' => $doc->id]);
    }

    public function test_admin_pages_are_blocked_for_lower_roles(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        foreach (['viewer', 'officer', 'reviewer'] as $role) {
            $this->actingAs($this->user($role, $org, $d));
            foreach (['/users', '/departments', '/organizations', '/audit-logs'] as $url) $this->get($url)->assertForbidden();
        }
        $this->actingAs($this->user('org_admin', $org))->get('/organizations')->assertForbidden();
        $this->get('/users')->assertOk();
    }

    public function test_org_admin_cannot_edit_users_of_another_organization(): void
    {
        $a = $this->org(); $b = $this->org();
        $admin = $this->user('org_admin', $a);
        $victim = $this->user('viewer', $b);
        $this->actingAs($admin)->get('/users/' . $victim->id . '/edit')->assertForbidden();
        $this->put('/users/' . $victim->id, ['name' => 'x', 'email' => 'x@x.test', 'role' => 'viewer'])->assertForbidden();
        $this->assertSame($victim->email, $victim->fresh()->email);
    }

    public function test_dept_admin_cannot_assign_privileged_roles(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        $da = $this->user('dept_admin', $org, $d);
        $this->actingAs($da)->post('/users', ['name' => 'N', 'email' => 'n@x.test', 'password' => 'Password@1', 'role' => 'org_admin'])->assertSessionHasErrors('role');
        $this->post('/users', ['name' => 'N', 'email' => 'n@x.test', 'password' => 'Password@1', 'role' => 'super_admin'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'n@x.test']);
    }

    public function test_user_cannot_change_own_role_or_status(): void
    {
        $org = $this->org();
        $admin = $this->user('org_admin', $org);
        $this->actingAs($admin)->put('/users/' . $admin->id, ['name' => 'a', 'email' => $admin->email, 'role' => 'org_admin'])->assertForbidden();
    }

    public function test_org_admin_department_is_always_created_in_own_organization(): void
    {
        $a = $this->org(); $b = $this->org();
        $this->actingAs($this->user('org_admin', $a))->post('/departments', ['name' => 'Sneaky', 'organization_id' => $b->id, 'is_active' => 1]);
        $this->assertDatabaseHas('departments', ['name' => 'Sneaky', 'organization_id' => $a->id]);
        $this->assertDatabaseMissing('departments', ['name' => 'Sneaky', 'organization_id' => $b->id]);
    }

    public function test_org_admin_cannot_edit_department_of_another_organization(): void
    {
        $a = $this->org(); $b = $this->org(); $dep = $this->dept($b);
        $this->actingAs($this->user('org_admin', $a))->put('/departments/' . $dep->id, ['name' => 'Renamed'])->assertForbidden();
    }

    public function test_role_change_by_org_admin_is_audited(): void
    {
        $org = $this->org(); $d = $this->dept($org);
        $admin = $this->user('org_admin', $org); $target = $this->user('viewer', $org, $d);
        $this->actingAs($admin)->put('/users/' . $target->id, ['name' => $target->name, 'email' => $target->email,
            'role' => 'reviewer', 'department_id' => $d->id, 'is_active' => 1])->assertRedirect('/users');
        $this->assertSame('reviewer', $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'subject_id' => $target->id]);
    }

    public function test_only_super_admin_can_create_organizations(): void
    {
        $org = $this->org();
        $this->actingAs($this->user('org_admin', $org))->post('/organizations', ['name' => 'Evil Org', 'code' => 'evil'])->assertForbidden();
        $this->actingAs($this->user('super_admin'))->post('/organizations', ['name' => 'New Org', 'code' => 'new-org', 'is_active' => 1])->assertRedirect('/organizations');
        $this->assertDatabaseHas('organizations', ['code' => 'new-org']);
    }

    public function test_audit_log_is_scoped_to_organization(): void
    {
        $a = $this->org(); $b = $this->org();
        $adminA = $this->user('org_admin', $a); $userB = $this->user('viewer', $b);
        \App\Services\AuditLogger::log('secret.org_b_event', null, [], $userB);
        $this->actingAs($adminA)->get('/audit-logs')->assertOk()->assertDontSee('secret.org_b_event');
    }

    public function test_page_output_is_escaped(): void
    {
        $org = $this->org();
        $this->doc($org, null, ['title' => '<script>alert(1)</script>']);
        $this->actingAs($this->user('org_admin', $org))->get('/documents')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
