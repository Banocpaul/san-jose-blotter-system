<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlotterCaseController;
use App\Http\Controllers\CaseManagementController;
use App\Http\Controllers\CaseWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediationController;
use App\Http\Controllers\LuponMediationController;
use App\Http\Controllers\HearingScheduleController;
use App\Http\Controllers\SettlementResolutionController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WitnessController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/login');


/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get(
        '/login',
        [AuthController::class, 'showLogin']
    )->name('login');


    Route::post(
        '/login',
        [AuthController::class, 'login']
    )->name('login.attempt');

});


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | Business Intelligence Analytics
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    |
    */

    Route::get(
        '/analytics',
        [AnalyticsController::class, 'index']
    )
        ->middleware(
            'role:barangay_captain,secretary'
        )
        ->name(
            'analytics.index'
        );


    /*
    |--------------------------------------------------------------------------
    | Reports & Export
    |--------------------------------------------------------------------------
    |
    | Barangay Captain and Secretary may generate operational case reports,
    | print filtered results, and export the same filtered data to Excel.
    |
    */

    Route::middleware(
        'role:barangay_captain,secretary'
    )->group(function () {

        Route::get(
            '/reports',
            [ReportController::class, 'index']
        )->name(
            'reports.index'
        );

        Route::get(
            '/reports/print',
            [ReportController::class, 'print']
        )->name(
            'reports.print'
        );

        Route::get(
            '/reports/export',
            [ReportController::class, 'export']
        )->name(
            'reports.export'
        );

    });


    /*
    |--------------------------------------------------------------------------
    | People Directory - View / Create / Edit
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    | Staff
    |
    */

    Route::middleware(
        'role:barangay_captain,secretary,staff'
    )->group(function () {


        /*
        |--------------------------------------------------------------------------
        | People Directory
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/residents',
            [ResidentController::class, 'index']
        )->name(
            'residents.index'
        );


        /*
        |--------------------------------------------------------------------------
        | Fast People Directory Search
        |--------------------------------------------------------------------------
        |
        | Used by searchable person selectors. The endpoint returns a small
        | result set instead of loading the full People Directory into a form.
        |
        */

        Route::get(
            '/residents/search',
            [ResidentController::class, 'search']
        )->name(
            'residents.search'
        );


        /*
        |--------------------------------------------------------------------------
        | Add Person Record
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | This must appear before /residents/{resident}
        | so Laravel does not interpret "create" as a resident ID.
        |
        */

        Route::get(
            '/residents/create',
            [ResidentController::class, 'create']
        )->name(
            'residents.create'
        );


        /*
        |--------------------------------------------------------------------------
        | Save Person Record
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/residents',
            [ResidentController::class, 'store']
        )->name(
            'residents.store'
        );


        /*
        |--------------------------------------------------------------------------
        | View Person Record
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/residents/{resident}',
            [ResidentController::class, 'show']
        )->name(
            'residents.show'
        );


        /*
        |--------------------------------------------------------------------------
        | Edit Person Record
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/residents/{resident}/edit',
            [ResidentController::class, 'edit']
        )->name(
            'residents.edit'
        );


        /*
        |--------------------------------------------------------------------------
        | Update Person Record
        |--------------------------------------------------------------------------
        */

        Route::match(
            ['put', 'patch'],
            '/residents/{resident}',
            [ResidentController::class, 'update']
        )->name(
            'residents.update'
        );

    });


    /*
    |--------------------------------------------------------------------------
    | People Directory - Archive / Restore
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    |
    */

    Route::middleware(
        'role:barangay_captain,secretary'
    )->group(function () {


        Route::delete(
            '/residents/{resident}',
            [ResidentController::class, 'destroy']
        )->name(
            'residents.destroy'
        );


        Route::patch(
            '/residents/{id}/restore',
            [ResidentController::class, 'restore']
        )->name(
            'residents.restore'
        );

    });


    /*
    |--------------------------------------------------------------------------
    | Case Management - Central Tracking
    |--------------------------------------------------------------------------
    |
    | Uses the same record-level visibility rules as the blotter module.
    | KPI counts and case listings are scoped to the current user's access.
    |
    */

    Route::get(
        '/case-management',
        [CaseManagementController::class, 'index']
    )
        ->middleware(
            'role:barangay_captain,secretary,staff,councilor,lupon'
        )
        ->name(
            'cases.index'
        );


    /*
    |--------------------------------------------------------------------------
    | Lupon & Mediation - Proceeding Management
    |--------------------------------------------------------------------------
    |
    | Captain and Secretary see all active proceedings.
    | Lupon members see only cases assigned to them.
    |
    */

    Route::get(
        '/lupon-mediation',
        [LuponMediationController::class, 'index']
    )
        ->middleware(
            'role:barangay_captain,secretary,lupon'
        )
        ->name(
            'lupon.index'
        );


    /*
    |--------------------------------------------------------------------------
    | Hearing Schedules
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/hearing-schedules',
        [HearingScheduleController::class, 'index']
    )
        ->middleware(
            'role:barangay_captain,secretary,lupon'
        )
        ->name(
            'hearings.index'
        );


    /*
    |--------------------------------------------------------------------------
    | Settlement & Resolutions
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/settlement-resolutions',
        [SettlementResolutionController::class, 'index']
    )
        ->middleware(
            'role:barangay_captain,secretary,lupon'
        )
        ->name(
            'settlements.index'
        );

    Route::patch(
        '/settlement-resolutions/{resolution}/finalize',
        [SettlementResolutionController::class, 'finalize']
    )
        ->middleware(
            'role:barangay_captain,secretary'
        )
        ->name(
            'settlements.finalize'
        );

    Route::patch(
        '/settlement-resolutions/{resolution}/resolve',
        [SettlementResolutionController::class, 'resolve']
    )
        ->middleware(
            'role:barangay_captain,secretary'
        )
        ->name(
            'settlements.resolve'
        );



    /*
    |--------------------------------------------------------------------------
    | Blotter Cases - Create / Edit
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    | Staff
    |
    */

    Route::middleware(
        'role:barangay_captain,secretary,staff'
    )->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Create Blotter Case
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/blotter/create',
            [BlotterCaseController::class, 'create']
        )->name(
            'blotter.create'
        );


        /*
        |--------------------------------------------------------------------------
        | Store Blotter Case
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/blotter',
            [BlotterCaseController::class, 'store']
        )->name(
            'blotter.store'
        );


        /*
        |--------------------------------------------------------------------------
        | Edit Blotter Case
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/blotter/{blotter}/edit',
            [BlotterCaseController::class, 'edit']
        )->name(
            'blotter.edit'
        );


        /*
        |--------------------------------------------------------------------------
        | Update Blotter Case
        |--------------------------------------------------------------------------
        */

        Route::match(
            ['put', 'patch'],
            '/blotter/{blotter}',
            [BlotterCaseController::class, 'update']
        )->name(
            'blotter.update'
        );

    });


    /*
    |--------------------------------------------------------------------------
    | Blotter Cases - View
    |--------------------------------------------------------------------------
    |
    | All operational roles may access the blotter module.
    |
    | Record-level policies determine which individual cases
    | Councilors and Lupon Members may actually access.
    |
    */

    Route::middleware(
        'role:barangay_captain,secretary,staff,councilor,lupon'
    )->group(function () {


        Route::get(
            '/blotter',
            [BlotterCaseController::class, 'index']
        )->name(
            'blotter.index'
        );


        Route::get(
            '/blotter/{blotter}',
            [BlotterCaseController::class, 'show']
        )->name(
            'blotter.show'
        );

    });


    /*
    |--------------------------------------------------------------------------
    | Blotter Cases - Archive
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    |
    */

    Route::delete(
        '/blotter/{blotter}',
        [BlotterCaseController::class, 'destroy']
    )
        ->middleware(
            'role:barangay_captain,secretary'
        )
        ->name(
            'blotter.destroy'
        );


    /*
    |--------------------------------------------------------------------------
    | Case Assignment
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    |
    */

    Route::post(
        '/blotter/{blotter}/assign',
        [
            CaseWorkflowController::class,
            'assign'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary'
        )
        ->name(
            'blotter.assign'
        );


    /*
    |--------------------------------------------------------------------------
    | Investigation Notes
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    | Assigned Councilor
    |
    */

    Route::post(
        '/blotter/{blotter}/investigation-notes',
        [
            CaseWorkflowController::class,
            'addInvestigationNote'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary,councilor'
        )
        ->name(
            'blotter.investigation-notes.store'
        );


    /*
    |--------------------------------------------------------------------------
    | Witness Management
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    | Assigned Councilor
    |
    | The BlotterCasePolicy performs the record-level check.
    |
    */

    Route::middleware(
        'role:barangay_captain,secretary,councilor'
    )->group(function () {


        Route::post(
            '/blotter/{blotter}/witnesses',
            [
                WitnessController::class,
                'store'
            ]
        )->name(
            'blotter.witnesses.store'
        );


        Route::put(
            '/witnesses/{witness}',
            [
                WitnessController::class,
                'update'
            ]
        )->name(
            'witnesses.update'
        );


        Route::delete(
            '/witnesses/{witness}',
            [
                WitnessController::class,
                'destroy'
            ]
        )->name(
            'witnesses.destroy'
        );

    });


    /*
    |--------------------------------------------------------------------------
    | Refer Case To Mediation
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    | Assigned Councilor
    |
    */

    Route::post(
        '/blotter/{blotter}/refer-mediation',
        [
            MediationController::class,
            'refer'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary,councilor'
        )
        ->name(
            'blotter.mediation.refer'
        );


    /*
    |--------------------------------------------------------------------------
    | Mediation - Schedule Hearing
    |--------------------------------------------------------------------------
    |
    | Barangay Captain
    | Secretary
    |
    | Lupon Members do NOT schedule or assign themselves.
    |
    */

    Route::post(
        '/blotter/{blotter}/mediation/schedule',
        [
            MediationController::class,
            'schedule'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary'
        )
        ->name(
            'blotter.mediation.schedule'
        );


    /*
    |--------------------------------------------------------------------------
    | Mediation - Summons
    |--------------------------------------------------------------------------
    |
    | Captain / Secretary may manage any hearing.
    | Lupon may manage only their assigned hearing,
    | enforced by MediationSessionPolicy.
    |
    */

    Route::patch(
        '/mediation/summons/{summons}',
        [
            MediationController::class,
            'updateSummons'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary,lupon'
        )
        ->name(
            'mediation.summons.update'
        );


    /*
    |--------------------------------------------------------------------------
    | Mediation - Attendance
    |--------------------------------------------------------------------------
    */

    Route::patch(
        '/mediation/attendees/{attendee}',
        [
            MediationController::class,
            'updateAttendance'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary,lupon'
        )
        ->name(
            'mediation.attendance.update'
        );


    /*
    |--------------------------------------------------------------------------
    | Mediation - Outcome
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/mediation/sessions/{session}/outcome',
        [
            MediationController::class,
            'recordOutcome'
        ]
    )
        ->middleware(
            'role:barangay_captain,secretary,lupon'
        )
        ->name(
            'mediation.outcome.store'
        );


    /*
    |--------------------------------------------------------------------------
    | Administration - User Management
    |--------------------------------------------------------------------------
    |
    | Barangay Captain only.
    |
    */

    Route::middleware(
        'role:barangay_captain'
    )
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {


            /*
            |--------------------------------------------------------------------------
            | User List
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/users',
                [
                    UserController::class,
                    'index'
                ]
            )->name(
                'users.index'
            );


            /*
            |--------------------------------------------------------------------------
            | Create User
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/users/create',
                [
                    UserController::class,
                    'create'
                ]
            )->name(
                'users.create'
            );


            /*
            |--------------------------------------------------------------------------
            | Store User
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/users',
                [
                    UserController::class,
                    'store'
                ]
            )->name(
                'users.store'
            );


            /*
            |--------------------------------------------------------------------------
            | Edit User
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/users/{user}/edit',
                [
                    UserController::class,
                    'edit'
                ]
            )->name(
                'users.edit'
            );


            /*
            |--------------------------------------------------------------------------
            | Update User
            |--------------------------------------------------------------------------
            */

            Route::put(
                '/users/{user}',
                [
                    UserController::class,
                    'update'
                ]
            )->name(
                'users.update'
            );


            /*
            |--------------------------------------------------------------------------
            | Activate / Deactivate User
            |--------------------------------------------------------------------------
            */

            Route::patch(
                '/users/{user}/toggle-status',
                [
                    UserController::class,
                    'toggleStatus'
                ]
            )->name(
                'users.toggle-status'
            );


            /*
            |--------------------------------------------------------------------------
            | Audit Trail
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/audit-trail',
                [
                    AuditLogController::class,
                    'index'
                ]
            )->name(
                'audit.index'
            );


            /*
            |--------------------------------------------------------------------------
            | Export Audit Trail
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/audit-trail/export',
                [
                    AuditLogController::class,
                    'export'
                ]
            )->name(
                'audit.export'
            );


            /*
            |--------------------------------------------------------------------------
            | Print Audit Logs
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/audit-trail/print',
                [
                    AuditLogController::class,
                    'print'
                ]
            )->name(
                'audit.print'
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/logout',
        [
            AuthController::class,
            'logout'
        ]
    )->name(
        'logout'
    );

});