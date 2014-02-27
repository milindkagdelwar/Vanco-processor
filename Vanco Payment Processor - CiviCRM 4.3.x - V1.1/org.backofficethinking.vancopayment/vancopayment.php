<?php

require_once 'vancopayment.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function vancopayment_civicrm_config(&$config) {
  _vancopayment_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function vancopayment_civicrm_xmlMenu(&$files) {
  _vancopayment_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function vancopayment_civicrm_install() {
  return _vancopayment_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function vancopayment_civicrm_uninstall() {
  return _vancopayment_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function vancopayment_civicrm_enable() {
  return _vancopayment_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function vancopayment_civicrm_disable() {
  return _vancopayment_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function vancopayment_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _vancopayment_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function vancopayment_civicrm_managed(&$entities) {
  return _vancopayment_civix_civicrm_managed($entities);
}



function vancopayment_civicrm_pre( $op, $objectName, $id, &$params ) {
	if( $objectName == 'Contribution' ) {
		//Get template values
		$smarty =  CRM_Core_Smarty::singleton( );
        $values =& $smarty->get_template_vars( );
		if ( $values['paymentProcessor']['class_name'] == 'vanco.directpayment.processor') {
			if ( is_numeric( $values['payment_method'] ) ) {
				$params['payment_instrument_id'] = $values['payment_method'];
			} else {
				require_once "CRM/Contribute/PseudoConstant.php";
				$paymentInstrument = CRM_Contribute_PseudoConstant::paymentInstrument();
				$params['payment_instrument_id'] = array_search( $values['payment_method'], $paymentInstrument );
			}
		}
	}
}

function vancopayment_civicrm_buildForm($formName, &$form ) {   
    //Buildform customization for vanco
    if ( $form->_paymentProcessor['class_name'] == 'vanco.directpayment.processor' 
		|| ( $formName == 'CRM_Contribute_Form_Contribution' && $form->_mode ) 
		|| ( $formName == 'CRM_Event_Form_Participant' && $form->_mode == 'live') ) {
        if ( $formName == 'CRM_Event_Form_Participant' || $formName == 'CRM_Contribute_Form_Contribution_Main' || $formName == 'CRM_Event_Form_Registration_Register' || $formName == 'CRM_Contribute_Form_Contribution' ) {
            require_once 'CRM/Contribute/PseudoConstant.php';

            $paymentMethods = CRM_Contribute_PseudoConstant::paymentInstrument();
            foreach ( $paymentMethods as $methodId => $methodName ) {
                if ( $methodName != "Credit Card" && $methodName != "ACH" ) {
                    unset($paymentMethods[$methodId]);
                }
            }
            //
            $form->assign('isVanco', 1);
            $element =& $form->add( 'select', 'payment_method', 
                                    ts( 'Payment Method' ), 
                                    array( '' => ts( '- select -' )) + $paymentMethods, TRUE, 
                                    array( 'onChange' => "return showHidePaymentDetails(this);")
                                    );
            $element =& $form->add( 'text', 'routing_number', ts('Routing Number'), array('size' => 15, 'maxlength' => 10, 'autocomplete' => 'off'));
            $element =& $form->add( 'text', 'account_number', ts('Account Number'), array('size' => 15, 'autocomplete' => 'off'));
            $element =& $form->add( 'select', 'account_type', 
                                    ts( 'Account Type' ), 
                                    array('checking' => ts('Checking'),
                                          'savings' => ts('Savings') ), TRUE ); 
        } 
        elseif ( $formName == 'CRM_Contribute_Form_Contribution_Confirm' || $formName == 'CRM_Event_Form_Registration_Confirm' || $formName == 'CRM_Contribute_Form_Contribution_ThankYou') {
            if( $formName == 'CRM_Event_Form_Registration_Confirm' ) {
				$params = $form->getVar('_params');
			} else { 
				$params = $form->_params;
			}
            if ( is_array($params[0]) ) {
                $params = $params[0];
            }			
            require_once 'CRM/Contribute/PseudoConstant.php';
            require_once 'CRM/Utils/System.php';
            $paymentMethods = CRM_Contribute_PseudoConstant::paymentInstrument();
			
            if ( CRM_Utils_Array::value( 'payment_method', $params ) ) {
                $form->assign( 'payment_method', $paymentMethods[$params['payment_method']]);
            }
            if ($paymentMethods[$params['payment_method']] == 'ACH') {
                if ( CRM_Utils_Array::value( 'routing_number', $params ) ) {
                    $form->assign( 'routing_number', $params['routing_number']);
                }
                if ( CRM_Utils_Array::value( 'account_number', $params ) ) {
                    $form->assign( 'account_number', CRM_Utils_System::mungeCreditCard( $params['account_number'] ) );
                }
                if ( CRM_Utils_Array::value( 'account_type', $params ) ) {
                    $form->assign( 'account_type', $params['account_type']);
                }
            }
	    }
    } else if ( $formName == 'CRM_Contribute_Form_ContributionPage_Amount' ) {
        $processorClass = getProcessorClass( $form->getVar('_id') );
        $form->addElement( 'checkbox', 'is_recur', ts('Recurring contributions'), null, 
                           array('onclick' => "showHideByValue('is_recur',true,'recurFields','table-row','radio',false); showRecurInterval( );") );
        require_once 'CRM/Core/OptionGroup.php';
        if( $processorClass == 'vanco.directpayment.processor' ){
            $frequencyUnitOptions = array( 'month'   => 'month',
                                           'week'    => 'week',
                                           'year'    => 'year'
                                           );
        }else{
            $frequencyUnitOptions = CRM_Core_OptionGroup::values( 'recur_frequency_units', false, false, false, null, 'name' );
            $form->addElement('checkbox', 'is_recur_interval', ts('Support recurring intervals') );
        }
        $form->addCheckBox( 'recur_frequency_unit', ts('Supported recurring units'), 
                            $frequencyUnitOptions,
                            null, null, null, null,
                            array( '&nbsp;&nbsp;', '&nbsp;&nbsp;', '&nbsp;&nbsp;', '<br/>' ) );
       
        foreach ( $form->_elements as $key => $val ) {
            if ( $val->_label == 'Support recurring intervals' ) {
                unset($form->_elements[$key]);
            }
        }
    }
  }

function vancopayment_civicrm_validate( $formName, &$fields, &$files, &$form ){
    if( $formName == 'CRM_Admin_Form_PaymentProcessor' ){
        unset( $form->_errors['url_api'] );
       
        unset( $form->_errors['test_url_api'] );
    }
    if( $formName == 'CRM_Contribute_Form_ContributionPage_Amount' && isset( $fields ['is_recur'] ) ){
        require_once 'CRM/Contribute/Form/ContributionPage/Amount.php';
       
        $defaults = array();
        require_once "CRM/Financial/BAO/PaymentProcessor.php";
        
        $processor_id = array('id' => $fields['payment_processor_id'] );
        
        CRM_Financial_BAO_PaymentProcessor::retrieve($processor_id, $defaults );
       
        $frequency_interval=array ( 'month' , 'week' , 'biweek' , 'quarter' , 'year'); 
        $flag = 0;
        if ( !empty( $fields['recur_frequency_unit'] ) ) {
            foreach( $frequency_interval as $key=>$values ){
                if ( !array_key_exists("$values",$fields['recur_frequency_unit'] )){
                    $flag = 0;
                }
                else{
                    $flag = 1;
                    break;
                }
            }
        }
        if ( $defaults['class_name'] == 'vanco.directpayment.processor' && $flag == 0){
            $form->_errors['recur_frequency_unit'] = "Invalid fields for the Payment Processor";
        }
    }
    require_once 'CRM/Contribute/PseudoConstant.php';
    $paymentMethods = CRM_Contribute_PseudoConstant::paymentInstrument();
    //override validation for CC or ACH details
    if ( $formName == 'CRM_Event_Form_Participant' || $formName == 'CRM_Contribute_Form_Contribution_Main' || $formName == 'CRM_Event_Form_Registration_Register' || $formName == 'CRM_Contribute_Form_Contribution' ) {
	   //Modified by BOT -7th Nov, 2012
	   //To unset validation errors when pay later is selected
		if ( ($formName == "CRM_Contribute_Form_Contribution_Main" || $formName == 'CRM_Event_Form_Registration_Register' ) && $fields['is_pay_later'] == 1 ) {
			unset($form->_errors['cvv2']);
			unset($form->_errors['payment_method']);
			unset($form->_errors['credit_card_number']);
			unset($form->_errors['credit_card_exp_date']);
			unset($form->_errors['credit_card_type']);
			unset($form->_errors['routing_number']);
			unset($form->_errors['account_number']);
		}      

	  if ( $formName == 'CRM_Contribute_Form_Contribution_Main' && $fields['is_recur'] == 0 ){ 
            require_once "CRM/Utils/Rule.php";
           
            if ( !empty($fields['amount_other'])) {
                $amt = CRM_Utils_Rule::cleanMoney( $fields['amount_other'] );
                if ($amt <= 5 ){
                    $form->_errors['amount_other'] = "Amount cannot be less than or equal to 5 for one time payment";  
                }
            } else if ( isset($fields['amount']) ) {
                $amt = CRM_Utils_Rule::cleanMoney( $fields['amount'] );
                if( $amt <= 5 ) {
                    $form->_errors['amount'] = "Amount cannot be less than or equal to 5 for recurring payment";
                } 
            }
        }
        if ( $paymentMethods[$fields['payment_method']] == 'ACH' ) {
            unset($form->_errors['credit_card_number']);
            unset($form->_errors['cvv2']);
            unset($form->_errors['credit_card_exp_date']);
            unset($form->_errors['credit_card_type']);
			//Modified by BOT -7th Nov, 2012
			//Add these errors only if pay later is not selected
			if( !$fields['is_pay_later'] ) {
				if ( !$fields['routing_number'] ) {
					$form->_errors['routing_number'] = "Routing number field is required";
				}
				if ( !$fields['account_number'] ) {
					$form->_errors['account_number'] = "Account number field is required";
				}
			}
        }
     }
}

function vancopayment_civicrm_tokens( &$tokens ){
    $tokens['contribution'] = array( '{contribution.errorLog}' => 'Vanco error log');
}

function vancopayment_civicrm_tokenValues( &$values, &$contactIDs ) {
    require_once 'CRM/Core/DAO.php';
    $customExt = CRM_Core_DAO::getFieldValue( 'CRM_Core_DAO_OptionValue', 'Custom Extensions', 'value', 'label' );
    
    $myFile = $customExt.'/vanco.directpayment.processor/packages/Vanco/log/vanco_error_log_'.date('Ymd').'.xml';
    if( file_exists( $myFile ) ){
        $fh = fopen($myFile, 'r');
        $theData = fread($fh, filesize($myFile));
        fclose($fh);
        if( is_array( $contactIDs ) ){
            $values[$contactIDs[0]]['contribution.errorLog'] = $theData;
        } else {
            $values['contribution.errorLog'] = $theData;
        }
    }
} 

/*Function to get payment processor class for given page id*/
/*Returns payment processor's class*/
function getProcessorClass( $pageId ){
    $sql = "SELECT pp.class_name 
    FROM `civicrm_contribution_page` cp, civicrm_payment_processor pp 
    where cp.payment_processor = pp.id and cp.id={$pageId}";
    $processorClass =& CRM_Core_DAO::singleValueQuery( $sql );
    return $processorClass;
}