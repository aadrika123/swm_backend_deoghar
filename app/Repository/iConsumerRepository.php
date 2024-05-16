<?php

namespace App\Repository;

use Illuminate\Http\Request;

/**
 * | Created On-
 * | Created By-
 * | The Consumer Interface for Consumer Repository
 */
interface iConsumerRepository
{
    public function ConsumerList(Request $request);

    public function ApartmentList(Request $request);

    public function getApartmentDetails(Request $request);

    public function ConsumerAdd(Request $request);

    public function RenterFormData(Request $request);

    public function EditConsumerDetailsById(Request $request);

    public function DeactivateConsumer(Request $request);

    public function PaymentData(Request $request);

    public function CalculatedAmount(Request $request);

    public function DashboardData(Request $request);

    public function GetTrancation(Request $request);

    public function TransactionDeactivate(Request $request);

    public function AddRenter(Request $request);

    public function makePayment(Request $request);

    public function AddGeoTagging(Request $request);

    public function GeoLocation(Request $request);

    public function TransactionModeChange(Request $request);

    public function AllTransaction(Request $request);

    public function AllCollectionSummary(Request $request);

    public function ConsumerUpdate(Request $request);

    public function AddCosumerReminder(Request $request);

    public function GetCosumerReminder(Request $request);

    public function makeApartmentPayment(Request $request);

    public function DeactivateApartment(Request $request);

    public function GetCaseVerificationList(Request $request);

    public function getCashVerificationFullDetails(Request $request);

    public function CashVerification(Request $request);

    public function ClearanceForm(Request $request);

    public function BankReconciliationList(Request $request);

    public function ConsumerListByCategory(Request $request);

    public function PaymentDeny(Request $request);

    public function PaymentDenyList(Request $request);

    public function GetReprintData(Request $request);

    public function GetDemandReceipt(Request $request);

    public function DenialNotificationList(Request $request);

    public function PaymentAdjustment(Request $request);

    public function PaymentAdjustmentList(Request $request);

    public function ConsumerOrApartmentList(Request $request);

    public function GetReminderList(Request $request);

    public function ConsumerPastTransactions(Request $request);

    public function addTcComplain(Request $request);

    public function getTcComplain(Request $request);

    public function addRoute(Request $request);

    public function RouteList(Request $request);

    public function RouteDataById(Request $request);

    public function updateRoute(Request $request);

    public function DeleteRoute(Request $request);
    
    public function AnalyticDashboardData(Request $request);
}
