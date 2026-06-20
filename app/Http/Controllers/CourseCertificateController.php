<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseCertificateResource;
use App\Models\Course;
use App\Models\CourseCertificate;
use App\Services\CertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CourseCertificateController extends Controller
{
    public function __construct(private readonly CertificateService $certificates) {}

    /**
     * GET /api/courses/{course}/certificate
     *
     * Returns the issued certificate, or eligibility status if not yet issued.
     */
    public function show(Request $request, Course $course)
    {
        $user        = $request->user();
        $certificate = $this->certificates->getCertificate($user, $course);

        if ($certificate) {
            $certificate->load(['user', 'course']);

            return $this->success(CourseCertificateResource::make($certificate));
        }

        $eligibility = $this->certificates->eligibilityStatus($user, $course);

        return response()->json([
            'status'      => 'not_issued',
            'message'     => 'لم تُصدر شهادة لهذا الكورس بعد.',
            'data'        => null,
            'eligibility' => $eligibility,
        ], 404);
    }

    /**
     * POST /api/courses/{course}/certificate
     *
     * Request certificate issuance. Idempotent — safe to call multiple times.
     * Returns 201 on first issuance, 200 if already issued.
     */
    public function store(Request $request, Course $course)
    {
        $certificate = $this->certificates->requestCertificate($request->user(), $course);

        $certificate->load(['user', 'course']);

        $justCreated = $certificate->wasRecentlyCreated;

        return $this->success(
            CourseCertificateResource::make($certificate),
            $justCreated ? 'تم إصدار الشهادة بنجاح. يمكنك تحميلها الآن.' : 'الشهادة مُصدرة مسبقاً.',
            $justCreated ? 201 : 200
        );
    }

    /**
     * GET /api/my-certificates
     *
     * Returns all certificates issued to the authenticated student.
     */
    public function myAll(Request $request)
    {
        $certificates = CourseCertificate::where('user_id', $request->user()->id)
            ->with(['user', 'course'])
            ->latest('issued_at')
            ->get();

        return $this->success(CourseCertificateResource::collection($certificates));
    }

    /**
     * GET /api/certificates/verify/{code}  — public, no auth required
     *
     * Returns certificate data if the code exists, or 404 if not found.
     */
    public function verify(string $code)
    {
        $certificate = CourseCertificate::where('certificate_code', $code)
            ->with(['user', 'course'])
            ->first();

        if (! $certificate) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'لم يتم العثور على شهادة بهذا الرمز.',
                'data'    => null,
            ], 404);
        }

        return $this->success(CourseCertificateResource::make($certificate));
    }

    /**
     * GET /api/courses/{course}/certificate/download
     *
     * Streams the certificate as a PDF attachment.
     * The frontend must fetch this with the Authorization header and handle
     * the blob response (see API documentation for the recommended approach).
     */
    public function download(Request $request, Course $course)
    {
        $certificate = $this->certificates->getCertificate($request->user(), $course);

        if (! $certificate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'لا توجد شهادة لتحميلها. قم بطلب إصدار الشهادة أولاً.',
            ], 404);
        }

        $certificate->load(['user', 'course']);

        $pdf = Pdf::loadView('certificates.certificate', [
            'certificate' => $certificate,
        ])->setPaper('a4', 'landscape');

        $filename = 'certificate-'.$certificate->certificate_code.'.pdf';

        return $pdf->download($filename);
    }
}
