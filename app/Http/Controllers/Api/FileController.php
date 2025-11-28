<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\FileRequest;
use App\Services\VirusScanService;
use App\Services\FileCreditsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function __construct(
        private VirusScanService $virusScanService,
        private FileCreditsService $creditsService,
    ) {}

    /**
     * Get all file categories
     */
    public function categories(): JsonResponse
    {
        $categories = FileCategory::active()
            ->ordered()
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'file_count' => $cat->file_count,
                'total_size' => $cat->formatted_size,
            ]);

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * List files in a category
     */
    public function index(Request $request, int $categoryId): JsonResponse
    {
        $category = FileCategory::findOrFail($categoryId);

        $files = File::with('uploader:id,handle')
            ->approved()
            ->inCategory($categoryId)
            ->orderByDesc('approved_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ],
            'files' => collect($files->items())->map(fn($f) => $this->formatFile($f)),
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Get file details
     */
    public function show(int $fileId): JsonResponse
    {
        $file = File::with(['category', 'uploader:id,handle', 'approvedBy:id,handle'])
            ->findOrFail($fileId);

        return response()->json([
            'success' => true,
            'file' => $this->formatFile($file, true),
        ]);
    }

    /**
     * Allowed file extensions for upload (security whitelist)
     */
    private const ALLOWED_EXTENSIONS = [
        // Archives
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'lha', 'lzh', 'arj',
        // Documents
        'txt', 'nfo', 'diz', 'doc', 'docx', 'pdf', 'rtf', 'odt',
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'webp',
        // Audio
        'mp3', 'wav', 'ogg', 'flac', 'mod', 'xm', 's3m', 'it', 'sid',
        // Video
        'mp4', 'avi', 'mkv', 'webm',
        // BBS specific
        'ans', 'asc', 'diz', 'nfo',
    ];

    /**
     * Blocked MIME types (security blacklist) 
     */
    private const BLOCKED_MIMES = [
        'application/x-php',
        'application/x-httpd-php',
        'application/x-sh',
        'application/x-csh',
        'application/x-executable',
        'application/x-msdownload',
        'text/x-php',
        'text/html',
        'application/javascript',
    ];

    /**
     * Upload a file
     */
    public function upload(Request $request): JsonResponse
    {
        $allowedExtensions = implode(',', self::ALLOWED_EXTENSIONS);
        
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:file_categories,id',
            'file' => [
                'required',
                'file',
                'max:102400', // 100MB max
                "mimes:{$allowedExtensions}",
            ],
            'description' => 'nullable|string|max:2000',
            'file_id_diz' => 'nullable|string|max:1000|regex:/^[^<>]*$/', // No HTML tags
        ], [
            'file.mimes' => __('files.invalid_file_type'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('files.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        
        // Additional security check: verify MIME type isn't blocked
        $mimeType = $uploadedFile->getMimeType();
        if (in_array($mimeType, self::BLOCKED_MIMES)) {
            return response()->json([
                'success' => false,
                'message' => __('files.dangerous_file_type'),
            ], 422);
        }
        
        // Security: sanitize filename
        $originalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        
        $md5Hash = md5_file($uploadedFile->getRealPath());

        // Check for duplicates
        $duplicate = File::findDuplicate($md5Hash);
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => __('files.duplicate_found'),
                'duplicate_id' => $duplicate->id,
            ], 409);
        }

        // Generate unique storage path
        $storagePath = 'files/' . date('Y/m') . '/' . Str::uuid() . '_' . $originalName;

        // Store file
        $uploadedFile->storeAs(dirname($storagePath), basename($storagePath));

        // Create file record
        $file = File::create([
            'category_id' => $request->category_id,
            'uploader_id' => $user->id,
            'filename' => $originalName,
            'storage_path' => $storagePath,
            'file_id_diz' => $request->file_id_diz,
            'description' => $request->description,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType(),
            'md5_hash' => $md5Hash,
            'status' => File::STATUS_PENDING,
        ]);

        // Virus scan the uploaded file
        $scanResult = $this->virusScanService->scanFile(
            $uploadedFile->getRealPath(),
            $file
        );
        
        if ($scanResult['virus_detected'] ?? false) {
            // File already quarantined and rejected by the service
            return response()->json([
                'success' => false,
                'message' => __('files.virus_detected'),
                'virus_name' => $scanResult['virus_name'] ?? 'Unknown',
            ], 422);
        }

        // Record upload stats for ratio system
        $this->creditsService->recordUpload($user, $file);

        // Auto-approve for staff
        if ($user->isStaff()) {
            $file->approve($user);
            $message = __('files.uploaded_approved');
        } else {
            $message = __('files.uploaded_pending');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'file' => $this->formatFile($file),
        ], 201);
    }

    /**
     * Download a file
     */
    public function download(Request $request, int $fileId): JsonResponse
    {
        $file = File::findOrFail($fileId);
        $user = $request->user();

        // Check ratio and credits with the credits service
        $canDownload = $this->creditsService->canDownload($user, $file);
        
        if (!$canDownload['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $canDownload['reason'],
                'credits_required' => $canDownload['cost'] ?? 0,
                'credits_available' => $user->credits,
                'ratio' => $this->creditsService->getRatio($user),
                'min_ratio' => config('bbs.files.min_ratio', 0.0),
            ], 403);
        }

        // Charge download and record stats
        $this->creditsService->chargeDownload($user, $file);

        // Generate temporary download URL
        $downloadUrl = Storage::temporaryUrl($file->storage_path, now()->addMinutes(5));

        return response()->json([
            'success' => true,
            'download_url' => $downloadUrl,
            'filename' => $file->filename,
            'credits_remaining' => $user->fresh()->credits,
        ]);
    }

    /**
     * Search files
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3|max:100',
            'category_id' => 'nullable|exists:file_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('files.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $results = File::search(
            $request->query,
            $request->category_id,
            50
        );

        return response()->json([
            'success' => true,
            'query' => $request->query,
            'results' => $results->map(fn($f) => $this->formatFile($f)),
            'count' => $results->count(),
        ]);
    }

    /**
     * Get new files since date
     */
    public function newFiles(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = $user->last_login_at ?? now()->subDays(7);

        $files = File::getNewFiles($since, 50);

        return response()->json([
            'success' => true,
            'since' => $since->toIso8601String(),
            'files' => $files->map(fn($f) => $this->formatFile($f)),
            'count' => $files->count(),
        ]);
    }

    /**
     * Get top uploaders
     */
    public function topUploaders(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 20), 100);
        $uploaders = File::getTopUploaders($limit);

        return response()->json([
            'success' => true,
            'uploaders' => $uploaders,
        ]);
    }

    /**
     * Get pending files (COSYSOP+)
     */
    public function pending(Request $request): JsonResponse
    {
        if (!$request->user()->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('files.unauthorized'),
            ], 403);
        }

        $files = File::with(['category', 'uploader:id,handle'])
            ->pending()
            ->orderBy('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'files' => collect($files->items())->map(fn($f) => $this->formatFile($f)),
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Approve a file (COSYSOP+)
     */
    public function approve(Request $request, int $fileId): JsonResponse
    {
        if (!$request->user()->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('files.unauthorized'),
            ], 403);
        }

        $file = File::findOrFail($fileId);
        $file->approve($request->user());

        return response()->json([
            'success' => true,
            'message' => __('files.approved'),
        ]);
    }

    /**
     * Reject a file (COSYSOP+)
     */
    public function reject(Request $request, int $fileId): JsonResponse
    {
        if (!$request->user()->isStaff()) {
            return response()->json([
                'success' => false,
                'message' => __('files.unauthorized'),
            ], 403);
        }

        $file = File::findOrFail($fileId);
        $file->reject($request->user());

        return response()->json([
            'success' => true,
            'message' => __('files.rejected'),
        ]);
    }

    /**
     * File requests - list open
     */
    public function requests(): JsonResponse
    {
        $requests = FileRequest::getOpenRequests(50);

        return response()->json([
            'success' => true,
            'requests' => $requests->map(fn($r) => [
                'id' => $r->id,
                'filename' => $r->filename_requested,
                'description' => $r->description,
                'user' => [
                    'id' => $r->user->id,
                    'handle' => $r->user->handle,
                ],
                'created_at' => $r->created_at,
            ]),
        ]);
    }

    /**
     * Create file request
     */
    public function createRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('files.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $fileRequest = FileRequest::createRequest(
            $request->user(),
            $request->filename,
            $request->description
        );

        return response()->json([
            'success' => true,
            'message' => __('files.request_created'),
            'request' => $fileRequest,
        ], 201);
    }

    /**
     * Fulfill file request
     */
    public function fulfillRequest(Request $request, int $requestId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|exists:files,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('files.validation_error'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $fileRequest = FileRequest::findOrFail($requestId);

        if (!$fileRequest->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => __('files.request_closed'),
            ], 400);
        }

        $file = File::findOrFail($request->file_id);
        $fileRequest->fulfill($request->user(), $file);

        return response()->json([
            'success' => true,
            'message' => __('files.request_fulfilled'),
        ]);
    }

    /**
     * Get user's upload/download ratio
     */
    public function ratio(Request $request): JsonResponse
    {
        $user = $request->user();
        $ratioInfo = $this->creditsService->getRatioInfo($user);

        return response()->json([
            'success' => true,
            'uploads' => $ratioInfo['total_uploads'],
            'downloads' => $ratioInfo['total_downloads'],
            'upload_bytes' => $ratioInfo['upload_bytes'],
            'download_bytes' => $ratioInfo['download_bytes'],
            'ratio' => $ratioInfo['ratio'],
            'min_ratio' => config('bbs.files.min_ratio', 0.0),
            'credits' => $user->credits,
            'can_download' => $ratioInfo['can_download'],
        ]);
    }

    /**
     * Format file for response
     */
    private function formatFile(File $file, bool $full = false): array
    {
        $data = [
            'id' => $file->id,
            'filename' => $file->filename,
            'file_size' => $file->file_size,
            'formatted_size' => $file->formatted_size,
            'download_count' => $file->download_count,
            'credits_cost' => $file->credits_cost,
            'status' => $file->status,
            'category' => $file->category ? [
                'id' => $file->category->id,
                'name' => $file->category->name,
            ] : null,
            'uploader' => $file->uploader ? [
                'id' => $file->uploader->id,
                'handle' => $file->uploader->handle,
            ] : null,
            'created_at' => $file->created_at,
            'approved_at' => $file->approved_at,
        ];

        if ($full) {
            $data['description'] = $file->description;
            $data['file_id_diz'] = $file->file_id_diz;
            $data['mime_type'] = $file->mime_type;
            $data['md5_hash'] = $file->md5_hash;
            $data['virus_scanned'] = $file->virus_scanned;
            $data['virus_scan_result'] = $file->virus_scan_result;
        }

        return $data;
    }
}
