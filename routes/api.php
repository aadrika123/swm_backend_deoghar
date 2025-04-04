<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenController;
use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');                              // Route for user Login
    Route::get('getHomePageData/{userId}', 'GetHomePageData');  // Route for get last Login Details
    Route::post('postChangePassword', 'ChangePassword');        // Route for Change Password
    Route::post('logOut', 'Logout');                            // Route for user Logout
    // Route::post('createUser', 'CreateUser');                    // Route for create user

    // Route::post('getAlluser', 'getAllUser');                    // Route for get all user
    Route::post('userActiveDeactive', 'userActiveDeactive');    // Route for user activate and deactivate
    // Route::post('getUserFormDate', 'getUserFormDate');          // Route for user form data
    Route::post('getTcList', 'getTcList');                      // Route for get tc list ulb wise
    Route::post('getTcListOnly', 'getOnlyTcList');
    Route::post('ulbSwitch', 'ulbSwitch');                      // Route for ulb switching
});

Route::group(['middleware' => ['json.response', 'apiauth:sanctum']], function () {
    // Your Protected Route is Here
    // Route::get('test', function () {
    //     return 'Success';
    // });
    Route::controller(AuthController::class)->group(function () {
        Route::post('updateUser', 'UpdateUser');                    // Route for update user

        // Menu Permission
        Route::post('postMenuPermission', 'MenuPermission');
        Route::get('getMenuPermissionList', 'MenuPermissionList');
        Route::post('getMenuPermissionById', 'MenuPermissionList');
        Route::post('updateMenuPermission', 'UpdateMenuPermission');
        Route::post('getMenuPermissionByUserType', 'MenuPermissionByUserType');

        // route for the users
        # edited by sam
        Route::post('getAlluser', 'getAllUser');                    // Route for get all user
        Route::post('createUser', 'CreateUser');                    // Route for create user
        Route::post('getUserFormDate', 'getUserFormDate');          // Route for user form data
        Route::get('get-user', 'getUser');                          // Route for get user
        # ended here                  
    });




    Route::controller(ConsumerController::class)->group(function () {
        Route::get('getConsumerList', 'GetConsumerList', function () {
            return 'Success';
        });
        Route::get('getConsumerDetailsById/{id}', 'GetConsumerList');
        Route::get('getApartmentList', 'GetApartmentList');
        Route::get('getApartmentDetailsById/{id}', 'GetApartmentDetailsById');
        Route::post('postConsumerAdd', 'postConsumerAdd');
        Route::get('getRenterFormData/{consumerId}', 'GetRenterFormData');
        Route::get('getEditConsumerDetailsbyId/{id}', 'getEditConsumerDetailsById');
        Route::post('postDeactivateConsumer', 'postDeactivateConsumer');
        Route::post('getPaymentData', 'getPaymentData');
        // Route::post('postPayment', 'MakePayment');
        Route::post('getCalculatedAmount', 'getCalculatedAmount');
        Route::post('getDashboardData', 'getDashboardData');
        Route::get('searchTransaction/{transactionNo}', 'searchTransaction');
        Route::post('transactionDeactivate', 'transactionDeactivate');
        Route::post('postRenterForn', 'RenterForm');

        // Geo Tagging
        Route::post('postGeoTagging', 'AddGeoTagging');
        Route::post('getGeoLocation', 'GetGeoLocation');

        Route::post('getAllTransaction', 'GetAllTransaction');
        Route::post('getCollectionSummary', 'AllCollectionSummary');
        Route::post('postEditConsumerDetail', 'UpdateConsumerDetails');
        Route::post('transactionModeChange', 'transactionModeChange');
        Route::post('postReminder', 'addConsumerReminder');
        Route::post('getReminder', 'getConsumerReminder');
        Route::post('apartmentPayment', 'ApartmentPayment');
        Route::post('apartmentDeactivate', 'ApartmentDeactivate');
        Route::post('getCashVerificationList', 'getCashVerificationList');
        Route::post('getCashVerificationFullDetails', 'getCashVerificationFullDetails');
        Route::post('postCashVerification', 'CashVerification');
        Route::post('getChequeDdDetails', 'getChequeDdDetails');

        Route::post('postClearanceForm', 'ClearanceForm');                           // Route for make bank reconciliation
        Route::post('getBankReconciliationList', 'GetBankReconciliationList');        // Route for get bank reconciliation

        Route::post('ApartmentDetailsById', 'GetApartmentDetailsById');
        Route::post('getConsumerListByCategory', 'ConsumerListByCategory');
        Route::post('postPaymentDeny', 'PaymentDeny');
        Route::post('getPaymentDenyList', 'PaymentDenyList');

        Route::post('getReprintData', 'getReprintData');
        Route::post('getDemandReceipt', 'GetDemandReceipt');
        Route::post('getdenialNotification', 'DenialNotificationList');
        Route::post('getAnalyticDashboardData', 'getAnalyticDashboardData');

        // Payment adjustments
        Route::post('paymentAdjustment', 'PaymentAdjustment');
        Route::get('getPaymentAdjustmentList', 'PaymentAdjustmentList');

        Route::post('getPaymentAdjustmentListV1', 'PaymentAdjustmentList');


        Route::post('consumerListByWardNo', 'ConsumerOrApartmentList');
        Route::post('getReminderList', 'GetReminderList');
        Route::post('tcReminderList', 'tcReminderList');
        Route::post('getConsumerPastTransactions', 'ConsumerPastTransactions');

        # Online Payment        
        Route::post('generate-order-id', 'generateOrderId');
        Route::post('save-order-response', 'saveOrderResponse');

        // For Complain
        Route::post('postTcComplain', 'TcComplain');
        Route::post('getComplainList', 'getComplainList');
        Route::post('v2/getComplainList', 'getTcComplainV2');
        Route::post('getComplainDetails', 'getComplainDetails');
        Route::post('switchStatus', 'switchStatus');
        Route::post('complainResolved', 'complainResolved');

        // For Routes
        Route::post('postNewRoute', 'addRoute');
        Route::post('getRouteList', 'RouteList');
        Route::post('getRouteDataById', 'RouteDataById');
        Route::post('updateRoute', 'updateRoute');
        Route::post('deleteRoute', 'DeleteRoute');

        Route::post('createDefaultConsumerApartment', 'DefaultConsumerApartment');
        Route::post('postPayment', 'MakePayment');
    });

    Route::controller(MasterController::class)->group(function () {
        Route::get('getConsumerAddFormData', 'GetConsumerAddFormData');
        Route::get('getApartmentListByWardNo/{wardNo}', 'GetApartmentListData');
        Route::get('getConsumerTypeByCategory/{id}', 'GetConsumerTypeByCategoryId');

        Route::post('updateApartment', 'updateApartment');
        Route::post('addApartment', 'addApartment');
        Route::get('getApartList', 'GetApartmentListData');
        Route::get('getApartmentById', 'getApartmentById');

        Route::get('getConsumerCategoryList', 'getConsumerCategoryList');
        Route::post('postConsumerCategoryAdd', 'ConsumerCategoryAdd');
        Route::put('postConsumerCategoryUpdate', 'ConsumerCategoryUpdate');
        Route::post('getConsumerCategoryById', 'ConsumerCategoryById');

        Route::post('getConsumerTypeList', 'ConsumerTypeList');
        Route::post('postConsumerTypeAdd', 'ConsumerTypeAdd');
        Route::put('postConsumerTypeUpdate', 'ConsumerTypeUpdate');
        Route::post('getConsumerTypeById', 'ConsumerTypeById');

        Route::post('getUlbList', 'UlbList');
        Route::post('postUlbAdd', 'UlbAdd');
        Route::put('postUlbUpdate', 'UlbUpdate');
        Route::post('deactivateToggleUlb', 'UlbActiveDeactive');
        Route::post('getUlbById', 'UlbById');

        Route::post('getWardList', 'WardList');
        Route::post('postWardAdd', 'WardAdd');
        Route::put('postWardUpdate', 'WardUpdate');
        Route::put('getWardListById', 'WardById');
    });


    Route::controller(ReportController::class)->group(function () {
        Route::get('test', 'text');
        Route::post('getReportData', 'GetReportData');                               // Route for get all type of report
        Route::post('monthlyComparison', 'monthlyComparison');                               // Route for get all type of report
        Route::post('consumer-edit-details', 'consumerEditLogDetails');
        Route::post('create-tc-geolocation', 'addTcGeoLocation');
        Route::post('list-tc-geolocation', 'tcGeolocationList');
        Route::post('get-tc-geolocation', 'getTcGeolocation');
        Route::post('getDemandReceiptData', 'GetDemandReceiptData');
        // Route::post('generateNextMonthDemand', 'generateNextMonthDemand');
    });
});

Route::controller(CitizenController::class)->group(function () {
    Route::post('citizen-ward-list', 'wardList');
    Route::post('search-residential-consumer', 'residentialConsumers');
    Route::post('search-commercial-consumer', 'commercialConsumers');
    Route::post('get-consumer-dtl', 'consumerDtl');
    Route::post('v2/get-consumer-dtl', 'consumerDtlV2');
    Route::post('payment-upto', 'paymentUpto');
    Route::post('apartment-list', 'apartmentList');
    Route::post('get-apartment', 'apartmentDtl');
    Route::post('get-apartment-by-id', 'apartmentDtlById');
    Route::post('calculate-amount', 'calculateAmount');
    Route::post('razorpay/initiate-payment', 'initiatePayment');
    Route::post('razorpay/save-response', 'saveRazorpayResponse');
    Route::post('consumer-type-list', 'listConsumerType');
    Route::post('tax-collector-list', 'listTaxCollector');
    Route::post('citizen-payment-receipt', 'paymentReceipt');
    Route::post('consumer-details', 'consumerDetailByConsumerNo');
    Route::post('post-citizen-complain', 'postCitizenComplain');
    Route::post('get-citizen-complain', 'getCitizenComplain');
    Route::post('citizen-complain-details', 'citizenComplainDetails');

    Route::post('send-otp', 'sendOtp');
    Route::post('verify-otp', 'verifyOtp');

    Route::post('sale-transaction', 'saleTransaction');
});

Route::controller(ConsumerController::class)->group(function () {
    // Route::post('postPayment', 'MakePayment');
    Route::post('getReprintData-v2', 'getReprintDatav2');

    //alok
    Route::post('is-active-model', 'isActiveModel');
});


