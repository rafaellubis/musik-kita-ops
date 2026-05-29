<?php
namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private Instrument $instrument;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->owner->assignRole('Owner');
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('Admin');
        $this->instrument = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_owner_bisa_lihat_daftar_template(): void
    {
        $this->actingAs($this->owner)->get('/report-templates')->assertOk();
    }

    public function test_admin_tidak_bisa_buat_template(): void
    {
        $this->actingAs($this->admin)
            ->post('/report-templates', ['name' => 'X', 'instrument_id' => $this->instrument->id])
            ->assertForbidden();
    }

    public function test_owner_bisa_buat_template(): void
    {
        $this->actingAs($this->owner)
            ->post('/report-templates', [
                'instrument_id' => $this->instrument->id,
                'name'          => 'Template Vocal',
                'description'   => 'Template untuk siswa vokal',
                'sort_order'    => 1,
            ])
            ->assertRedirect('/report-templates');

        $this->assertDatabaseHas('report_templates', ['name' => 'Template Vocal']);
    }

    public function test_owner_bisa_tambah_section(): void
    {
        $template = ReportTemplate::create([
            'instrument_id' => $this->instrument->id,
            'name'          => 'Template Vocal',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->actingAs($this->owner)
            ->post("/report-templates/{$template->id}/sections", [
                'title'      => 'Kemampuan Bernyanyi',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('report_template_sections', ['title' => 'Kemampuan Bernyanyi']);
    }

    public function test_owner_bisa_tambah_item_ke_section(): void
    {
        $template = ReportTemplate::create([
            'instrument_id' => $this->instrument->id,
            'name'          => 'Template Vocal',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $section = ReportTemplateSection::create([
            'report_template_id' => $template->id,
            'title'              => 'Bernyanyi',
            'sort_order'         => 1,
        ]);

        $this->actingAs($this->owner)
            ->post("/report-templates/{$template->id}/sections/{$section->id}/items", [
                'label'      => 'Teknik Pernafasan Diafragma',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('report_template_items', ['label' => 'Teknik Pernafasan Diafragma']);
    }
}
