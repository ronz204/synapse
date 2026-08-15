<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Document;
use App\Models\Modality;
use App\Models\ModalityResolution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fake demo data for RC-03 (Modality Catalog), built on top of the
 * DEMO-1xx courses CurriculumDemoSeeder already creates and the catalog
 * ModalitySeeder already inserts.
 *
 * Deliberately NOT called from DatabaseSeeder::run() — run it by hand,
 * after both:
 *
 *   php artisan db:seed --class=CurriculumDemoSeeder
 *   php artisan db:seed --class=ModalityAssignmentDemoSeeder
 *
 * Deliberately does NOT assign a resolution-requiring modality to any
 * course without a currently-valid resolution backing it: doing so would
 * leave the system in a state this slice's own write-time gate exists to
 * prevent. The one expired resolution seeded here is left unassigned on
 * its course for exactly that reason — it exists so the "expired doesn't
 * count" behavior can be inspected, not to model a broken assignment.
 */
class ModalityAssignmentDemoSeeder extends Seeder
{
    /** @var array<int, string> */
    private const REQUIRED_COURSE_CODES = ['DEMO-108', 'DEMO-110', 'DEMO-111', 'DEMO-112'];

    public function run(): void
    {
        $courses = Course::query()->whereIn('code', self::REQUIRED_COURSE_CODES)->get()->keyBy('code');

        if ($courses->count() < count(self::REQUIRED_COURSE_CODES)) {
            $this->command->warn('ModalityAssignmentDemoSeeder needs the DEMO-1xx courses — run CurriculumDemoSeeder first.');

            return;
        }

        $virtual = Modality::query()->where('name', 'Virtual')->first();
        $hybrid = Modality::query()->where('name', 'Híbrido')->first();

        if (! $virtual || ! $hybrid) {
            $this->command->warn('ModalityAssignmentDemoSeeder needs the base catalog — run ModalitySeeder first.');

            return;
        }

        // Two courses with a currently-valid resolution, assigned to that
        // modality — the golden path this slice exists to allow.
        $this->seedValidAssignment($courses['DEMO-108'], $virtual, 'R-2024-030');
        $this->seedValidAssignment($courses['DEMO-110'], $hybrid, 'R-2024-031');

        // One expired resolution on file, deliberately left unassigned on
        // its course — lets the "expired doesn't count as valid" behavior
        // be inspected without leaving any course in an invalid state.
        $this->seedExpiredResolution($courses['DEMO-111'], $virtual, 'R-2019-012');

        // DEMO-112 is left on the catalog's default (Presencial) — a course
        // that never needed a resolution in the first place.

        $this->command->info('Modality assignment demo data seeded: 2 valid course/modality assignments plus 1 expired, unassigned resolution.');
    }

    private function seedValidAssignment(Course $course, Modality $modality, string $resolutionNumber): void
    {
        $resolution = $this->seedResolution($course, $modality, $resolutionNumber, now()->subMonth(), null);
        $this->attachDemoDocument($resolution, $resolutionNumber);

        $course->modality_id = $modality->id;
        $course->save();
    }

    private function seedExpiredResolution(Course $course, Modality $modality, string $resolutionNumber): ModalityResolution
    {
        $resolution = $this->seedResolution($course, $modality, $resolutionNumber, now()->subYears(3), now()->subYears(2));
        $this->attachDemoDocument($resolution, $resolutionNumber);

        return $resolution;
    }

    private function seedResolution(Course $course, Modality $modality, string $resolutionNumber, \DateTimeInterface $validFrom, ?\DateTimeInterface $validTo): ModalityResolution
    {
        return ModalityResolution::query()->create([
            'course_id' => $course->id,
            'modality_id' => $modality->id,
            'resolution_number' => $resolutionNumber,
            'approving_body' => 'Consejo Universitario',
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
        ]);
    }

    /**
     * A real, tiny file actually written to the local disk — not just a
     * Document row with a dangling path — so the "view resolution" download
     * link works when browsing this demo data manually.
     */
    private function attachDemoDocument(ModalityResolution $resolution, string $resolutionNumber): void
    {
        $path = 'resoluciones/demo/'.Str::slug($resolutionNumber).'.pdf';
        $contents = "%PDF-1.4\n% Demo resolution document for {$resolutionNumber}\n";

        Storage::disk('local')->put($path, $contents);

        Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'documentable_type' => ModalityResolution::class,
            'documentable_id' => $resolution->id,
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
