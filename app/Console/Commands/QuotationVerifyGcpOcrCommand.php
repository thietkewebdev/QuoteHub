<?php

namespace App\Console\Commands;

use Google\ApiCore\ApiException;
use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\GetProcessorRequest;
use Illuminate\Console\Command;
use Throwable;

/**
 * Kiểm tra .env + file JSON service account và (tuỳ chọn) gọi Document AI GetProcessor.
 */
class QuotationVerifyGcpOcrCommand extends Command
{
    protected $signature = 'quotation:verify-gcp-ocr
                            {--skip-live : Chỉ kiểm tra file/env, không gọi API Google}';

    protected $description = 'Verify GCP credentials file and Document AI processor config (services.gcp)';

    public function handle(): int
    {
        $credPath = config('services.gcp.credentials_path');
        $project = trim((string) config('services.gcp.project_id', ''));
        $location = trim((string) config('services.gcp.location', ''));
        $processorId = trim((string) config('services.gcp.document_ai_processor_id', ''));

        $ok = true;

        if (! is_string($credPath) || trim($credPath) === '') {
            $this->error('GOOGLE_APPLICATION_CREDENTIALS / services.gcp.credentials_path đang trống.');
            $ok = false;
        } elseif (! is_file($credPath)) {
            $this->error('Không tìm thấy file credentials: '.$credPath);
            $ok = false;
        } elseif (! is_readable($credPath)) {
            $this->error('File credentials không đọc được: '.$credPath);
            $ok = false;
        } else {
            $json = json_decode((string) file_get_contents($credPath), true);
            if (! is_array($json)) {
                $this->error('File JSON không hợp lệ (không parse được).');
                $ok = false;
            } elseif (($json['type'] ?? '') !== 'service_account') {
                $this->warn('JSON không có type=service_account — có thể không phải key service account.');
                $ok = false;
            } else {
                $email = (string) ($json['client_email'] ?? '');
                $this->info('Credentials: OK (service account: '.($email !== '' ? $email : '?').').');
            }
        }

        foreach (
            [
                'GCP_PROJECT_ID' => $project,
                'GCP_LOCATION' => $location,
                'GCP_DOCUMENT_AI_PROCESSOR_ID' => $processorId,
            ] as $label => $value
        ) {
            if ($value === '') {
                $this->error($label.' / tương ứng trong config đang trống.');
                $ok = false;
            }
        }

        if (! $ok) {
            $this->newLine();
            $this->comment('Cấu hình trong .env: GOOGLE_APPLICATION_CREDENTIALS, GCP_PROJECT_ID, GCP_LOCATION, GCP_DOCUMENT_AI_PROCESSOR_ID');
            $this->comment('Config đọc từ config/services.php → key gcp.');

            return self::FAILURE;
        }

        $this->line('Project: '.$project);
        $this->line('Location: '.$location);
        $this->line('Processor ID: '.$processorId);

        if ($this->option('skip-live')) {
            $this->info('Đã bỏ qua gọi API (--skip-live).');

            return self::SUCCESS;
        }

        $processorName = sprintf('projects/%s/locations/%s/processors/%s', $project, $location, $processorId);
        $this->comment('Processor resource: '.$processorName);

        if (is_string($credPath) && is_file($credPath) && is_readable($credPath)) {
            $real = realpath($credPath) ?: $credPath;
            putenv('GOOGLE_APPLICATION_CREDENTIALS='.$real);
            $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $real;
        }

        $client = new DocumentProcessorServiceClient;
        try {
            $processor = $client->getProcessor(GetProcessorRequest::build($processorName));
            $state = method_exists($processor, 'getState') ? $processor->getState() : null;
            $displayName = method_exists($processor, 'getDisplayName') ? $processor->getDisplayName() : '';
            $this->info('Document AI GetProcessor: OK.');
            if ($displayName !== '') {
                $this->line('Display name: '.$displayName);
            }
            if ($state !== null) {
                $this->line('State: '.(string) $state);
            }
        } catch (ApiException $e) {
            $this->error('Document AI API: '.$e->getMessage());
            $this->comment('Kiểm tra: API Document AI đã bật, service account có role Document AI / quyền phù hợp, processor đúng region.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $client->close();
        }

        $this->newLine();
        $this->info('Vision API dùng chung credentials; có thể thử: php artisan quotation:test-ocr path/to/anh.jpg');

        return self::SUCCESS;
    }
}
