<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SchemeController;
use App\Http\Controllers\Api\TrainerController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /* ------------------------------ Auth ------------------------------ */
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:6,1');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        /* ----------------------------- Vendor ----------------------------- */
        Route::apiResource('vendors', VendorController::class);
        Route::post('vendors/{vendor}/transition', [VendorController::class, 'transition']);
        Route::post('vendors/{vendor}/centers', [VendorController::class, 'addCenter']);
        Route::post('vendors/{vendor}/centers/{center}/inspect', [VendorController::class, 'inspectCenter']);
        Route::post('vendors/{vendor}/centers/{center}/transition', [VendorController::class, 'transitionCenter']);
        Route::put('vendors/{vendor}/kyc', [VendorController::class, 'upsertKyc']);
        Route::post('vendors/{vendor}/kyc/transition', [VendorController::class, 'transitionKyc']);

        /* ----------------------------- Trainer ---------------------------- */
        Route::apiResource('trainers', TrainerController::class);
        Route::post('trainers/{trainer}/transition', [TrainerController::class, 'transition']);
        Route::post('trainers/{trainer}/assignments', [TrainerController::class, 'assign']);

        /* ---------------------------- Candidate --------------------------- */
        Route::apiResource('candidates', CandidateController::class);
        Route::post('candidates/{candidate}/eligibility-check', [CandidateController::class, 'eligibilityCheck']);
        Route::post('candidates/{candidate}/enrollments', [CandidateController::class, 'enroll']);
        Route::patch('enrollments/{enrollment}', [CandidateController::class, 'updateEnrollment']);
        Route::put('enrollments/{enrollment}/assessment', [CandidateController::class, 'putAssessment']);
        Route::put('enrollments/{enrollment}/certification', [CandidateController::class, 'putCertification']);
        Route::put('enrollments/{enrollment}/placement', [CandidateController::class, 'putPlacement']);
        Route::post('enrollments/{enrollment}/progress', [CandidateController::class, 'progressStatus']);

        /* ----------------------------- Scheme ----------------------------- */
        Route::apiResource('schemes', SchemeController::class);
        Route::put('schemes/{scheme}/eligibility-rules', [SchemeController::class, 'syncEligibilityRules']);
        Route::put('schemes/{scheme}/payment-milestones', [SchemeController::class, 'syncPaymentMilestones']);
        Route::put('schemes/{scheme}/job-roles', [SchemeController::class, 'syncJobRoles']);

        /* ---------------------------- Documents --------------------------- */
        Route::apiResource('documents', DocumentController::class)->only(['store', 'show']);
        Route::post('documents/{document}/transition', [DocumentController::class, 'transition']);

        /* --------------------------- Invoices ----------------------------- */
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/submit', [InvoiceController::class, 'submit']);
        Route::post('invoices/{invoice}/transition', [InvoiceController::class, 'transition']);
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);

        /* ----------------------------- Reports ---------------------------- */
        Route::get('reports/mis', [ReportController::class, 'mis']);
        Route::get('audits', [ReportController::class, 'audits']);
        Route::get('verifications', [ReportController::class, 'verifications']);
    });
});
