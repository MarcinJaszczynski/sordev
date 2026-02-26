<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use App\Livewire\ProgramPointChildrenEditor;
use App\Models\EventTemplateProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

class ProgramPointChildrenEditorSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
    }

    /** @test */
    public function unauthorized_user_cannot_access_component()
    {
        $programPoint = EventTemplateProgramPoint::factory()->create();

        // Livewire may wrap exceptions thrown during initial render into a ViewException.
        // Accept either a direct AuthenticationException or a ViewException that wraps it.
        try {
            Livewire::test(ProgramPointChildrenEditor::class, ['programPoint' => $programPoint]);
            $this->fail('Expected an authentication exception to be thrown when mounting component for unauthenticated user.');
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            $this->assertInstanceOf(\Illuminate\Auth\AuthenticationException::class, $e);
        } catch (\Illuminate\View\ViewException $e) {
            // Livewire sometimes wraps the original exception in a ViewException during rendering
            $this->assertInstanceOf(\Illuminate\Auth\AuthenticationException::class, $e->getPrevious());
        }
    }

    /** @test */
    public function authorized_user_can_access_component()
    {
        $this->actingAs($this->user);
        
        $programPoint = EventTemplateProgramPoint::factory()->create();

        $component = Livewire::test(ProgramPointChildrenEditor::class, ['programPoint' => $programPoint])
            ->assertStatus(200);
            
        $this->assertTrue(true); // Component loaded successfully
    }

    /** @test */
    public function search_term_is_limited_to_100_characters()
    {
        $this->actingAs($this->user);
        
        $programPoint = EventTemplateProgramPoint::factory()->create();
        $longSearchTerm = str_repeat('a', 150); // 150 characters

        $component = Livewire::test(ProgramPointChildrenEditor::class, ['programPoint' => $programPoint])
            ->set('searchTerm', $longSearchTerm)
            ->call('applyFilters');

        // Search term should be truncated to 100 characters
        $this->assertEquals(100, strlen($component->get('searchTerm')));
    }

    /** @test */
    public function invalid_child_id_is_rejected_on_save()
    {
        $this->actingAs($this->user);
        
        $programPoint = EventTemplateProgramPoint::factory()->create();

        $component = Livewire::test(ProgramPointChildrenEditor::class, ['programPoint' => $programPoint])
            ->set('modalData.child_program_point_id', 'invalid_id')
            ->call('saveChild')
            ->assertHasErrors(['modalData.child_program_point_id']);
    }

    /** @test */
    public function circular_reference_is_prevented()
    {
        $this->actingAs($this->user);
        
        $programPoint = EventTemplateProgramPoint::factory()->create();

        $component = Livewire::test(ProgramPointChildrenEditor::class, ['programPoint' => $programPoint])
            ->set('modalData.child_program_point_id', $programPoint->id)
            ->call('saveChild')
            ->assertHasErrors(['child_program_point_id']);
    }

    /** @test */
    public function only_positive_integers_accepted_for_delete()
    {
        $this->actingAs($this->user);
        
        $programPoint = EventTemplateProgramPoint::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        Livewire::test(ProgramPointChildrenEditor::class, ['programPoint' => $programPoint])
            ->call('deleteChild', -1);
    }
}
