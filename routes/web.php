<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

$tableExists = function (string $table): bool {
    try {
        return Schema::hasTable($table);
    } catch (Throwable $exception) {
        Log::warning("Could not inspect database table [{$table}].", [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return false;
    }
};

$siteSettings = fn (): array => $tableExists('site_settings')
    ? DB::table('site_settings')->orderBy('key')->pluck('value', 'key')->all()
    : [];

if (config('app.debug')) {
    Route::get('/debug-db', function () use ($tableExists) {
        try {
            $version = DB::selectOne('select version() as version');

            return response()->json([
                'ok' => true,
                'database' => config('database.connections.mysql.database'),
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'version' => $version?->version,
                'tables' => [
                    'users' => $tableExists('users'),
                    'documents' => $tableExists('documents'),
                    'site_settings' => $tableExists('site_settings'),
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    });
}

$eventResource = fn (object $item): array => [
    'id' => $item->id,
    'title' => $item->title,
    'description' => $item->description,
    'date' => $item->event_date,
    'location' => $item->location,
    'image' => $item->image_url,
];

Route::get('/', function () use ($siteSettings, $eventResource, $tableExists) {
    return Inertia::render('Portal/Home', [
        'settings' => $siteSettings(),
        'featuredNews' => $tableExists('news')
            ? DB::table('news')
                ->where('is_published', true)
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(9)
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'image' => $item->image_url,
                    'date' => $item->published_at,
                ])
            : collect(),
        'events' => $tableExists('events')
            ? DB::table('events')
                ->where('is_published', true)
                ->orderBy('event_date')
                ->orderBy('id')
                ->limit(3)
                ->get()
                ->map($eventResource)
            : collect(),
        'offices' => $tableExists('offices')
            ? DB::table('offices')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect(),
    ]);
})->name('home');

Route::get('/nosotros', function () use ($siteSettings, $tableExists) {
    return Inertia::render('Portal/About', [
        'settings' => $siteSettings(),
        'sections' => $tableExists('institutional_sections')
            ? DB::table('institutional_sections')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect(),
    ]);
})->name('about');

Route::get('/documentos', function (Request $request) use ($siteSettings, $tableExists) {
    $page = max(1, (int) $request->integer('page', 1));
    $pageSize = 4;
    $search = trim((string) $request->input('search', ''));
    $categories = $request->input('category', []);
    $categories = is_array($categories) ? array_values(array_filter($categories)) : [$categories];

    if (! $tableExists('documents') || ! $tableExists('document_categories')) {
        return Inertia::render('Portal/Documents', [
            'settings' => $siteSettings(),
            'categories' => collect(),
            'documents' => [
                'items' => collect(),
                'total' => 0,
                'page' => 1,
                'page_size' => $pageSize,
                'total_pages' => 1,
            ],
            'filters' => [
                'search' => $search,
                'category' => $categories,
            ],
        ]);
    }

    $query = DB::table('documents')
        ->join('document_categories', 'documents.category_id', '=', 'document_categories.id')
        ->where('documents.is_published', true)
        ->where('document_categories.is_active', true)
        ->select([
            'documents.id',
            'documents.title',
            'documents.published_date',
            'documents.format',
            'documents.file_size',
            'documents.file_url',
            'document_categories.name as category',
        ]);

    if ($search !== '') {
        $query->where('documents.title', 'like', "%{$search}%");
    }

    if ($categories !== []) {
        $query->whereIn('document_categories.name', $categories);
    }

    $total = (clone $query)->count();
    $items = $query
        ->orderByDesc('documents.published_date')
        ->orderByDesc('documents.id')
        ->forPage($page, $pageSize)
        ->get()
        ->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'category' => $item->category,
            'date' => $item->published_date,
            'format' => $item->format,
            'size' => $item->file_size,
            'url' => $item->file_url,
        ]);

    return Inertia::render('Portal/Documents', [
        'settings' => $siteSettings(),
        'categories' => DB::table('document_categories')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(),
        'documents' => [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => max(1, (int) ceil($total / $pageSize)),
        ],
        'filters' => [
            'search' => $search,
            'category' => $categories,
        ],
    ]);
})->name('documents');

Route::get('/eventos', function (Request $request) use ($siteSettings, $eventResource, $tableExists) {
    $page = max(1, (int) $request->integer('page', 1));
    $pageSize = 6;

    if (! $tableExists('events')) {
        return Inertia::render('Portal/Events', [
            'settings' => $siteSettings(),
            'events' => [
                'items' => collect(),
                'total' => 0,
                'page' => 1,
                'page_size' => $pageSize,
                'total_pages' => 1,
            ],
        ]);
    }

    $query = DB::table('events')->where('is_published', true);
    $total = (clone $query)->count();

    return Inertia::render('Portal/Events', [
        'settings' => $siteSettings(),
        'events' => [
            'items' => $query
                ->orderBy('event_date')
                ->orderBy('id')
                ->forPage($page, $pageSize)
                ->get()
                ->map($eventResource),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => max(1, (int) ceil($total / $pageSize)),
        ],
    ]);
})->name('events');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
