<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repository\iConsumerRepository;

class ConsumerController extends Controller
{
    /**
     * | Created On- 08-09-2022 
     * | Created By- 
     * | Controller Consumer related operations
     */

    //  Initializing construct function for Repositoy
    protected $consumer;
    public function __construct(iConsumerRepository $consumer)
    {
        $this->ConResp = $consumer;
    }



    /**
     * Get consumer list according to mobile no or name.
     *
     * @param  $id, $consumerNo, $consumerName, $mobileNo
     * @return ConsumerRepository->ConsumerList
     */
    public function GetConsumerList(Request $req)
    {
        return $this->ConResp->ConsumerList($req);
    }



    /**
     * Get apartment list.
     *
     * @param  $apartmentId, $apartmentName
     * @return ConsumerRepository->ApartmentList
     */
    public function GetApartmentList(Request $req)
    {
        return $this->ConResp->ApartmentList($req);
    }



    /**
     * Get apartment details by id.
     *
     * @param  $apartmentId
     * @return ConsumerRepository->getApartmentDetails
     */
    public function GetApartmentDetailsById(Request $req)
    {
        return $this->ConResp->getApartmentDetails($req);
    }



    /**
     * Add new consumer.
     *
     * @param  $request
     * @return ConsumerRepository->ConsumerAdd
     */
    public function postConsumerAdd(Request $req)
    {
        return $this->ConResp->ConsumerAdd($req);
    }
    
    /**
     * Get Analytic dashboard data.
     *
     * @param  $request
     * @return ConsumerRepository->AnalyticDashboardData
     */
    public function getAnalyticDashboardData(Request $req)
    {
        return $this->ConResp->AnalyticDashboardData($req);
    }



    /**
     * Data for add renter master setup.
     *
     * @param  $request
     * @return ConsumerRepository->RenterFormData
     */
    public function GetRenterFormData(Request $req)
    {
        return $this->ConResp->RenterFormData($req);
    }



    /**
     * Get consumer details by id.
     *
     * @param  $request
     * @return ConsumerRepository->EditConsumerDetailsById
     */
    public function getEditConsumerDetailsById(Request $req)
    {
        return $this->ConResp->EditConsumerDetailsById($req);
    }



    /**
     * Make consumer deactivate.
     *
     * @param  $request
     * @return ConsumerRepository->DeactivateConsumer
     */
    public function postDeactivateConsumer(Request $req)
    {
        return $this->ConResp->DeactivateConsumer($req);
    }



    /**
     * Get payment details.
     *
     * @param  $request
     * @return ConsumerRepository->PaymentData
     */
    public function getPaymentData(Request $req)
    {
        return $this->ConResp->PaymentData($req);
    }



    /**
     * Make payment.
     *
     * @param  $request
     * @return ConsumerRepository->makePayment
     */
    public function MakePayment(Request $req)
    {
        return $this->ConResp->makePayment($req);
    }



    /**
     * Get calculate demand amount by consumer.
     *
     * @param  $request
     * @return ConsumerRepository->CalculatedAmount
     */
    public function getCalculatedAmount(Request $req)
    {
        return $this->ConResp->CalculatedAmount($req);
    }



    /**
     * Get dashboard data.
     *
     * @param  $request
     * @return ConsumerRepository->DashboardData
     */
    public function getDashboardData(Request $req)
    {
        return $this->ConResp->DashboardData($req);
    }



    /**
     * Get transaction details by transaction no.
     *
     * @param  $request
     * @return ConsumerRepository->GetTrancation
     */
    public function searchTransaction(Request $req)
    {
        return $this->ConResp->GetTrancation($req);
    }



    /**
     * Make transaction deactivate.
     *
     * @param  $request
     * @return ConsumerRepository->TransactionDeactivate
     */
    public function transactionDeactivate(Request $req)
    {
        return $this->ConResp->TransactionDeactivate($req);
    }


    /**
     * Make transaction mode changed.
     *
     * @param  $request
     * @return ConsumerRepository->TransactionModeChange
     */
    public function transactionModeChange(Request $req)
    {
        return $this->ConResp->TransactionModeChange($req);
    }



    /**
     * Add new Renter.
     *
     * @param  $request
     * @return ConsumerRepository->AddRenter
     */
    public function RenterForm(Request $req)
    {
        return $this->ConResp->AddRenter($req);
    }

    /**
     * Add geo location.
     *
     * @param  $request
     * @return ConsumerRepository->AddGeoTagging
     */
    public function AddGeoTagging(Request $req)
    {
        return $this->ConResp->AddGeoTagging($req);
    }

    /**
     * Get geo location.
     *
     * @param  $request
     * @return ConsumerRepository->GeoLocation
     */
    public function GetGeoLocation(Request $req)
    {
        return $this->ConResp->GeoLocation($req);
    }



    /**
     * Get all transactions according to user and ward no.
     *
     * @param  $request
     * @return ConsumerRepository->AllTransaction
     */
    public function GetAllTransaction(Request $req)
    {
        return $this->ConResp->AllTransaction($req);
    }



    /**
     * Get Collection summery by the user.
     *
     * @param  $request
     * @return ConsumerRepository->AllCollectionSummary
     */
    public function AllCollectionSummary(Request $req)
    {
        return $this->ConResp->AllCollectionSummary($req);
    }



    /**
     * Get Collection summery by the user.
     *
     * @param  $request
     * @return ConsumerRepository->ConsumerUpdate
     */
    public function UpdateConsumerDetails(Request $req)
    {
        return $this->ConResp->ConsumerUpdate($req);
    }


    /**
     * Add new consumer reminder for payment.
     *
     * @param  $request
     * @return ConsumerRepository->AddCosumerReminder
     */
    public function addConsumerReminder(Request $req)
    {
        return $this->ConResp->AddCosumerReminder($req);
    }


    /**
     * get consumer reminder for payment.
     *
     * @param  $request
     * @return ConsumerRepository->GetCosumerReminder
     */
    public function getConsumerReminder(Request $req)
    {
        return $this->ConResp->GetCosumerReminder($req);
    }


    /**
     * Make apartment wise payment.
     *
     * @param  $request
     * @return ConsumerRepository->makeApartmentPayment
     */
    public function ApartmentPayment(Request $req)
    {
        return $this->ConResp->makeApartmentPayment($req);
    }



    /**
     * Make apartment deactivate.
     *
     * @param  $request
     * @return ConsumerRepository->DeactivateApartment
     */
    public function ApartmentDeactivate(Request $req)
    {
        return $this->ConResp->DeactivateApartment($req);
    }



    /**
     * Get cash verification list.
     *
     * @param  $request
     * @return ConsumerRepository->GetCaseVerificationList
     */
    public function getCashVerificationList(Request $req)
    {
        return $this->ConResp->GetCaseVerificationList($req);
    }


    /**
     * Get cash verification list.
     *
     * @param  $request
     * @return ConsumerRepository->getCashVerificationFullDetails
     */
    public function getCashVerificationFullDetails(Request $req)
    {
        return $this->ConResp->getCashVerificationFullDetails($req);
    }



    /**
     * Get cash verification list.
     *
     * @param  $request
     * @return ConsumerRepository->CashVerification
     */
    public function CashVerification(Request $req)
    {
        return $this->ConResp->CashVerification($req);
    }


    /**
     * Make Bank Reconcilliation.
     *
     * @param  $request
     * @return ConsumerRepository->ClearanceForm
     */
    public function ClearanceForm(Request $req)
    {
        return $this->ConResp->ClearanceForm($req);
    }


    /**
     * Get Bank Reconcilliation.
     *
     * @param  $request
     * @return ConsumerRepository->BankReconciliationList
     */
    public function GetBankReconciliationList(Request $req)
    {
        return $this->ConResp->BankReconciliationList($req);
    }



    /**
     * Get consumer list by category.
     *
     * @param  $request
     * @return ConsumerRepository->ConsumerListByCategory
     */
    public function ConsumerListByCategory(Request $req)
    {
        return $this->ConResp->ConsumerListByCategory($req);
    }


    /**
     * Make payment denied.
     *
     * @param  $request
     * @return ConsumerRepository->PaymentDeny
     */
    public function PaymentDeny(Request $req)
    {
        return $this->ConResp->PaymentDeny($req);
    }


    /**
     * Payment denied list.
     *
     * @param  $request
     * @return ConsumerRepository->PaymentDenyList
     */
    public function PaymentDenyList(Request $req)
    {
        return $this->ConResp->PaymentDenyList($req);
    }


    /**
     * Payment receipt date.
     *
     * @param  $request
     * @return ConsumerRepository->GetReprintData
     */
    public function getReprintData(Request $req)
    {
        return $this->ConResp->GetReprintData($req);
    }



    /**
     * Get Demand Receipt.
     *
     * @param  $request
     * @return ConsumerRepository->GetDemandReceipt
     */
    public function GetDemandReceipt(Request $req)
    {
        return $this->ConResp->GetDemandReceipt($req);
    }


    /**
     * Denial Notification.
     *
     * @param  $request
     * @return ConsumerRepository->DenialNotificationList
     */
    public function DenialNotificationList(Request $req)
    {
        return $this->ConResp->DenialNotificationList($req);
    }


    /**
     * Payment Adjustment
     *
     * @param  $request
     * @return ConsumerRepository->DenialNotificationList
     */
    public function PaymentAdjustment(Request $req)
    {
        return $this->ConResp->PaymentAdjustment($req);
    }


    /**
     * Consumer and apartment list by ward no.
     *
     * @param  $request
     * @return ConsumerRepository->ConsumerOrApartmentList
     */
    public function ConsumerOrApartmentList(Request $req)
    {
        return $this->ConResp->ConsumerOrApartmentList($req);
    }


    /**
     * Get reminder list
     *
     * @param  $request
     * @return ConsumerRepository->GetReminderList
     */
    public function GetReminderList(Request $req)
    {
        return $this->ConResp->GetReminderList($req);
    }

    /**
     * Get Consumer/Apartment Past Transaction
     *
     * @param  $request
     * @return ConsumerRepository->ConsumerPastTransactions
     */
    public function ConsumerPastTransactions(Request $req)
    {
        return $this->ConResp->ConsumerPastTransactions($req);
    }


    /**
     * Add Tc Complain 
     *
     * @param  $request
     * @return ConsumerRepository->addTcComplain
     */
    public function TcComplain(Request $req)
    {
        return $this->ConResp->addTcComplain($req);
    }

    /**
     * get Tc Complain 
     *
     * @param  $request
     * @return ConsumerRepository->getTcComplain
     */
    public function getComplainList(Request $req)
    {
        return $this->ConResp->getTcComplain($req);
    }


    /**
     * add routes 
     *
     * @param  $request
     * @return ConsumerRepository->addRoute
     */
    public function addRoute(Request $req)
    {
        return $this->ConResp->addRoute($req);
    }

    
    /**
     * get routes 
     *
     * @param  $request
     * @return ConsumerRepository->RouteList
     */
    public function RouteList(Request $req)
    {
        return $this->ConResp->RouteList($req);
    }
    
    /**
     * get route by id
     *
     * @param  $request
     * @return ConsumerRepository->RouteDataById
     */
    public function RouteDataById(Request $req)
    {
        return $this->ConResp->RouteDataById($req);
    }

    /**
     * update route
     *
     * @param  $request
     * @return ConsumerRepository->RouteDataById
     */
    public function updateRoute(Request $req)
    {
        return $this->ConResp->updateRoute($req);
    }

    /**
     * Delete route
     *
     * @param  $request
     * @return ConsumerRepository->RouteDataById
     */
    public function DeleteRoute(Request $req)
    {
        return $this->ConResp->DeleteRoute($req);
    }
    
    /**
     * payment adjustment list
     *
     * @param  $request
     * @return ConsumerRepository->PaymentAdjustmentList
     */
    public function PaymentAdjustmentList(Request $req)
    {
        return $this->ConResp->PaymentAdjustmentList($req);
    }

    
    
}
