<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Models\Course;
use App\Models\Document;
use App\Models\Equivalency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fake demo data for RC-02 (Equivalency graph integrity), built on top of
 * the DEMO-1xx courses CurriculumDemoSeeder already creates.
 *
 * Deliberately NOT called from DatabaseSeeder::run() — run it by hand, after
 * CurriculumDemoSeeder:
 *
 *   php artisan db:seed --class=CurriculumDemoSeeder
 *   php artisan db:seed --class=EquivalencyDemoSeeder
 *
 * Deliberately does NOT seed any cycle or unresolved contradiction: doing so
 * would leave the graph in a state its own acceptance criteria say must
 * never be reachable through normal use. Those scenarios belong in feature
 * tests, not in demo data a Superadmin might casually browse.
 */
class EquivalencyDemoSeeder extends Seeder
{
    /** @var array<int, string> */
    private const REQUIRED_COURSE_CODES = ['DEMO-101', 'DEMO-102', 'DEMO-103', 'DEMO-104', 'DEMO-105', 'DEMO-106', 'DEMO-107', 'DEMO-109'];

    public function run(): void
    {
        $courses = Course::query()->whereIn('code', self::REQUIRED_COURSE_CODES)->get()->keyBy('code');

        if ($courses->count() < count(self::REQUIRED_COURSE_CODES)) {
            $this->command->warn('EquivalencyDemoSeeder needs the DEMO-1xx courses — run CurriculumDemoSeeder first.');

            return;
        }

        $this->seedEquivalency($courses['DEMO-101'], $courses['DEMO-104'], EquivalencyDirection::OldToNew, 'R-2021-001');
        $this->seedEquivalency($courses['DEMO-102'], $courses['DEMO-105'], EquivalencyDirection::NewToOld, 'R-2021-002');
        $this->seedEquivalency($courses['DEMO-103'], $courses['DEMO-106'], EquivalencyDirection::Bidirectional, 'R-2021-003');

        // A superseded/superseding pair, so the catalog shows real history
        // instead of only ever-active rows.
        $superseded = $this->seedEquivalency($courses['DEMO-107'], $courses['DEMO-109'], EquivalencyDirection::OldToNew, 'R-2018-010', status: EquivalencyStatus::Superseded);
        $winner = $this->seedEquivalency($courses['DEMO-107'], $courses['DEMO-109'], EquivalencyDirection::OldToNew, 'R-2021-011');

        $superseded->superseded_by_id = $winner->id;
        $superseded->save();

        $this->command->info('Equivalency demo data seeded: 3 active equivalencies (OldToNew, NewToOld, Bidirectional) plus 1 superseded/superseding pair.');
    }

    private function seedEquivalency(Course $source, Course $target, EquivalencyDirection $direction, string $resolutionNumber, EquivalencyStatus $status = EquivalencyStatus::Active): Equivalency
    {
        // status/superseded_by_id are deliberately excluded from the model's
        // #[Fillable] (see App\Models\Equivalency) — forceCreate() is the
        // one place allowed to bypass that for demo/seed data.
        $equivalency = Equivalency::query()->forceCreate([
            'source_course_id' => $source->id,
            'target_course_id' => $target->id,
            'direction' => $direction,
            'resolution_number' => $resolutionNumber,
            'status' => $status,
        ]);

        $this->attachDemoDocument($equivalency, $resolutionNumber);

        return $equivalency;
    }

    /**
     * A real, tiny file actually written to the local disk — not just a
     * Document row with a dangling path — so the "view resolution" download
     * link works when browsing this demo data manually.
     */
    private function attachDemoDocument(Equivalency $equivalency, string $resolutionNumber): void
    {
        $path = 'resoluciones/demo/'.Str::slug($resolutionNumber).'.pdf';
        $contents = "%PDF-1.4\n% Demo resolution document for {$resolutionNumber}\n";

        Storage::disk('local')->put($path, $contents);

        Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'documentable_type' => Equivalency::class,
            'documentable_id' => $equivalency->id,
            'document_type' => 'Resolución',
            'original_name' => Str::slug($resolutionNumber).'.pdf',
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'hash_sha256' => hash('sha256', $contents),
        ]);
    }
}
