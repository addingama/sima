<?php

namespace Tests\Feature\Api;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function bendahara_can_upload_receipt_attachment_to_local_disk(): void
    {
        Storage::fake('local');

        $this->actingAsRole('bendahara');
        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);

        $receiptId = $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'transfer',
            'amount' => '100000.00',
            'allocations' => [['fund_id' => $fund->id, 'amount' => '100000.00']],
        ])->assertCreated()->json('data.id');

        $file = UploadedFile::fake()->image('bukti-transfer.jpg', 100, 100);

        $response = $this->post('/api/attachments', [
            'attachable_type' => 'receipt',
            'attachable_id' => $receiptId,
            'title' => 'Bukti transfer',
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Bukti transfer')
            ->assertJsonPath('data.original_name', 'bukti-transfer.jpg');

        $attachment = Attachment::query()->findOrFail($response->json('data.id'));

        $this->assertSame('local', $attachment->disk);
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
        $this->assertStringContainsString('/api/attachments/'.$attachment->id.'/download', (string) $response->json('data.url'));

        $this->getJson('/api/attachments?attachable_type=receipt&attachable_id='.$receiptId)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->get('/api/attachments/'.$attachment->id.'/download')
            ->assertOk();
    }

    #[Test]
    public function upload_rejects_unsupported_mime(): void
    {
        Storage::fake('local');
        $this->actingAsRole('bendahara');
        $admin = $this->makeUser('admin');
        $account = $this->makeAccount($admin);
        $fund = $this->makeFund($admin);

        $receiptId = $this->postJson('/api/receipts', [
            'receipt_date' => now()->toDateString(),
            'account_id' => $account->id,
            'channel' => 'cash',
            'amount' => '50000.00',
            'allocations' => [['fund_id' => $fund->id, 'amount' => '50000.00']],
        ])->assertCreated()->json('data.id');

        $this->post('/api/attachments', [
            'attachable_type' => 'receipt',
            'attachable_id' => $receiptId,
            'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
