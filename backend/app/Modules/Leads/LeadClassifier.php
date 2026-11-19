<?php

namespace App\Modules\Leads;

use App\Modules\Branches\Branch;
use App\Modules\Leads\Models\LeadType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Keyword-based detection of lead type and branch from free text. */
class LeadClassifier
{
    private ?Collection $types = null;

    private ?Collection $branches = null;

    public function type(string $text): ?LeadType
    {
        $text = Str::lower($text);
        $best = null;
        $bestScore = 0;

        foreach ($this->types() as $type) {
            $score = collect([...($type->keywords ?? []), $type->name])
                ->filter(fn ($keyword) => $this->mentions($text, $keyword))
                ->count();
            if ($score > $bestScore) {
                [$best, $bestScore] = [$type, $score];
            }
        }

        return $best;
    }

    public function branch(string $text): ?Branch
    {
        $text = Str::lower($text);

        return $this->branches()
            ->sortByDesc(fn (Branch $branch) => strlen($branch->name))
            ->first(fn (Branch $branch) => $this->mentions($text, $branch->name));
    }

    /** Matches an explicit value ("Birthday Party", "bday") to a type, falling back to keywords. */
    public function typeByName(?string $value): ?LeadType
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $needle = Str::lower($value);

        return $this->types()->first(fn (LeadType $t) => Str::lower($t->name) === $needle || $t->slug === Str::slug($value))
            ?? $this->type($value);
    }

    public function branchByName(?string $value): ?Branch
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $this->branches()->first(fn (Branch $b) => Str::lower($b->name) === Str::lower($value)) ?? $this->branch($value);
    }

    private function mentions(string $text, string $keyword): bool
    {
        $keyword = trim(Str::lower($keyword));

        return $keyword !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/u', $text) === 1;
    }

    private function types(): Collection
    {
        return $this->types ??= LeadType::where('active', true)->orderBy('sort')->get();
    }

    private function branches(): Collection
    {
        return $this->branches ??= Branch::where('active', true)->get();
    }
}
