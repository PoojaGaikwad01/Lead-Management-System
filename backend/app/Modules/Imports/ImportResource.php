<?php

namespace App\Modules\Imports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

/** @mixin Import */
class ImportResource extends JsonResource
{
    public bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status,
            'original_name' => $this->original_name,
            'sender_phone' => $this->sender_phone,
            'uploaded_by' => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null,
            'total_rows' => $this->total_rows,
            'created_count' => $this->created_count,
            'duplicate_count' => $this->duplicate_count,
            'error_count' => $this->error_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $data;
        }

        return [
            ...$data,
            'errors' => $this->errors ?? [],
            'mapping' => (object) ($this->mapping ?? []),
            'message_text' => $this->message?->body,
            ...($this->canRun() ? $this->mappingStep() : []),
        ];
    }

    private function mappingStep(): array
    {
        $preview = [];
        try {
            $sheet = SpreadsheetReader::read($this->file);
            foreach (array_slice($sheet['rows'], 0, 5) as $row) {
                $preview[] = array_map(fn ($i) => SpreadsheetReader::text($row[$i] ?? null), array_keys($sheet['headers']));
            }
        } catch (Throwable) {
        }

        return ['headers' => $this->headers ?? [], 'preview' => $preview, 'targets' => ColumnMapping::targets()];
    }
}
