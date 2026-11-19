<?php

namespace App\Modules\Imports;

use App\Modules\Inbox\Message;
use App\Modules\Leads\AgencyTextParser;
use App\Modules\Leads\AiExtractor;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\LeadIntake;
use App\Modules\Leads\PhoneNormalizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Turns an agency WhatsApp message (text or Excel/CSV file) into leads, recorded as an import. */
class AgencyMessageProcessor
{
    private const SPREADSHEETS = ['csv', 'xls', 'xlsx'];

    public function __construct(
        private ImportRunner $runner,
        private AgencyTextParser $parser,
        private AiExtractor $ai,
        private LeadIntake $intake,
    ) {}

    public function process(Message $message): ?Import
    {
        $extension = strtolower(pathinfo((string) $message->media_name, PATHINFO_EXTENSION));
        if ($message->media_url && in_array($extension, self::SPREADSHEETS, true)) {
            return $this->processFile($message);
        }

        return filled($message->body) ? $this->processText($message) : null;
    }

    private function processFile(Message $message): Import
    {
        $import = Import::create([
            'source' => LeadSource::AgencyFile,
            'file' => $message->media_url,
            'original_name' => $message->media_name,
            'message_id' => $message->id,
            'sender_phone' => $message->contact->phone,
        ]);

        try {
            $this->runner->prepare($import);
            if ($import->status === 'pending') {
                $this->runner->run($import, $import->mapping);
            }
        } catch (Throwable $e) {
            Log::error('Agency file import failed.', ['import_id' => $import->id, 'error' => $e->getMessage()]);
            $import->update(['status' => 'failed', 'error_count' => 1, 'errors' => [['row' => null, 'message' => 'The file could not be read.']]]);
        }

        return $import;
    }

    private function processText(Message $message): Import
    {
        $text = (string) $message->body;
        $entries = $this->parser->isFixedFormat($text) ? $this->parser->parse($text) : null;
        $entries ??= $this->ai->extract($text) ?? $this->parser->parse($text);

        $import = Import::create([
            'source' => LeadSource::AgencyText,
            'message_id' => $message->id,
            'sender_phone' => $message->contact->phone,
            'total_rows' => count($entries),
        ]);

        $created = $duplicates = 0;
        $errors = [];
        foreach ($entries as $i => $entry) {
            $phone = PhoneNormalizer::normalize($entry['phone'] ?? null);
            if (! $phone) {
                $errors[] = ['row' => $i + 1, 'message' => filled($entry['phone'] ?? null) ? "Invalid phone number \"{$entry['phone']}\"." : 'Phone number is missing.'];

                continue;
            }
            [$result] = $this->intake->upsert($phone, [...$entry, 'text' => $entry['text'] ?? $text], LeadSource::AgencyText, $import);
            $result === 'created' ? $created++ : $duplicates++;
        }

        if ($entries === []) {
            $errors[] = ['row' => null, 'message' => 'No phone number found in the message.'];
        }

        $import->update([
            'status' => 'completed',
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'error_count' => count($errors),
            'errors' => $errors ?: null,
        ]);

        return $import;
    }
}
