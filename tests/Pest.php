<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Creates a user holding exactly the named permissions (granted directly, so a
 * test states its own preconditions instead of depending on the seeders' role
 * definitions). Each permission row is created on the fly from its
 * "module.action" name.
 *
 * @param  array<int, string>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        [$module, $action] = explode('.', $name, 2);

        Permission::query()->firstOrCreate(
            ['name' => $name],
            ['module' => $module, 'action' => $action],
        );
    }

    $user->givePermissionTo(...$permissions);

    return $user->fresh();
}

/**
 * True when `resources/views/components/siga/sidebar.blade.php` rendered a
 * nav link for the given (already-translated) label. A plain assertSee()
 * against the whole dashboard response is not reliable for labels like
 * "Study Plans"/"Equivalencies" — the dashboard's own KPI cards render the
 * identical translated text outside the sidebar, so assertSee()/assertDontSee()
 * would pass or fail regardless of what the sidebar itself did. Matching the
 * exact `nav-text` markup the sidebar wraps every link label in disambiguates
 * the two.
 */
function sidebarShowsLink(TestResponse $response, string $label): bool
{
    return str_contains($response->getContent(), 'class="nav-text" data-labels>'.$label.'</span>');
}

/**
 * Swaps the PDF port for a spy and returns it. The real SpatiePdfExporter
 * shells out to headless Chromium — slow, and dependent on a Chrome binary
 * being present. Asserting against the port instead is precisely what the
 * Hexagonal boundary buys: component behaviour verified without the
 * infrastructure.
 */
function fakePdfExporter(): object
{
    $spy = new class implements PdfExporterInterface
    {
        public ?string $html = null;

        public ?string $paperSize = null;

        public function toBytes(string $html, string $paperSize = 'a4'): string
        {
            $this->html = $html;
            $this->paperSize = $paperSize;

            return 'fake-pdf-bytes';
        }
    };

    app()->instance(PdfExporterInterface::class, $spy);

    return $spy;
}

/**
 * Swaps the Excel port for a spy that records the fully-mapped rows, letting a
 * test assert on column labels and formatted values without parsing a real
 * spreadsheet.
 */
function fakeExcelExporter(): object
{
    $spy = new class implements ExcelExporterInterface
    {
        /** @var array<int, array<string, mixed>> */
        public array $rows = [];

        public ?string $filename = null;

        public function streamDownload(iterable $rows, string $filename): StreamedResponse
        {
            foreach ($rows as $row) {
                $this->rows[] = $row;
            }

            $this->filename = $filename;

            return new StreamedResponse(fn () => print ('xlsx'));
        }
    };

    app()->instance(ExcelExporterInterface::class, $spy);

    return $spy;
}
