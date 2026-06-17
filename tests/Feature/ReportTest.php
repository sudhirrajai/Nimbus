<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Nimbus can receive a local bug report submission
     * and correctly forward it to VmCoreCentral.
     */
    public function test_can_submit_bug_report_to_vmcorecentral()
    {
        $user = User::factory()->create();

        // Fake connection to VmCoreCentral bug-reports endpoint
        Http::fake([
            '*/api/v1/bug-reports' => Http::response([
                'status' => true,
                'message' => 'Bug report submitted successfully.'
            ], 201)
        ]);

        $screenshot = UploadedFile::fake()->image('screenshot.png');
        $attachment = UploadedFile::fake()->image('attachment.jpg');

        $response = $this->actingAs($user)->postJson('/bug-reports', [
            'message' => 'The system panel crashes on file save.',
            'screenshot' => $screenshot,
            'images' => [$attachment],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Bug report submitted successfully.'
        ]);

        // Check if the server-to-server request was sent
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/bug-reports');
        });
    }
}
