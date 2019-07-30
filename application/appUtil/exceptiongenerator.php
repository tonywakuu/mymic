<?php
class ExceptionGenerator {
    /**
     * Send success response message
     */
    public function sendSuccess($successNumber) {
        switch ($successNumber) {
            case 'RR001':
                $successMsg = 'Plan updated successfully.';
                break;
            case 'RR002':
                $successMsg = 'Plan added successfully.';
                break;
            case 'RR003':
                $successMsg = 'Channel activated successfully.';
                break;
            case 'RR004':
                $successMsg = 'Channel suspended successfully.';
                break;
            case 'RR005':
                $successMsg = 'User activated successfully.';
                break;
            case 'RR006':
                $successMsg = 'User suspended successfully.';
                break;
            case 'RR007':
                $successMsg = 'Broadcast activated successfully.';
                break;
            case 'RR008':
                $successMsg = 'Broadcast killed successfully.';
                break;
            case 'RR009':
                $successMsg = 'Successfully logout.';
                break;
            case 'RR0010':
                $successMsg = 'Password has been sent your prefered email address, please check your email.';
                break;
            case 'RR0011':
                $successMsg = 'User earned money updated successfully.';
                break;
            default:
                $successMsg = 'Success number ' . $successNumber;
                break;
        }
        return $successMsg;
    }
    /**
     * Function to send a error
     * @param type $errorNumber
     */
    public function sendError($errorNumber) {
        $errorData = array();
        $mongoUserResult['errors'] = array();
        switch ($errorNumber) {
            case 'EE003':
                $errorMsg = 'Username already exists';
                break;
            case 'EE006':
                $errorMsg = 'Invalid token or token has been expired';
                break;
            case 'EE001':
                $errorMsg = 'Incorrect username or password';
                break;
            case 'EE005':
                $errorMsg = 'Email already exists';
                break;
            case 'EE007':
                $errorMsg = 'Username cannot contain empty space';
                break;
            case 'EE008':
                $errorMsg = 'File type not supported';
                break;
            case 'EE009':
                $errorMsg = 'Token has been expired';
                break;
            case 'EE010':
                $errorMsg = 'Invalid Otp';
                break;
            case 'EE011':
                $errorMsg = 'Username cannot be empty';
                break;
            case 'EE013':
                $errorMsg = 'Invalid email address';
                break;
            case 'EE012':
                $errorMsg = 'Password cannot be empty';
                break;
            case 'EE014':
                $errorMsg = 'Your number is not registered in twillio account';
                break;
            case 'EE015':
                $errorMsg = 'Channelname already exists';
                break;
            case 'EE016':
                $errorMsg = 'Email id does not exist';
                break;
            case 'EE019':
                $errorMsg = 'Failed to update user profile';
                break;
            case 'EE020':
                $errorMsg = 'User id does not exist';
                break;
            case 'EE021':
                $errorMsg = 'Failed to retrieve application config';
                break;
            case 'EE018':
                $errorMsg = 'Record not found';
                break;
            case 'EE025':
                $errorMsg = 'We are unable to process your request right now. please try after some time';
                break;
            case 'EE026':
                $errorMsg = 'Content for the channel already exists in same time frame.';
                break;
            case 'EE027':
                $errorMsg = 'Start time can not be greater than end time.';
                break;
            case 'EE028':
                $errorMsg = 'Plan name can not be empty.';
                break;
            case 'EE029':
                $errorMsg = 'channel id does not exist.';
                break;
            case 'EE030':
                $errorMsg = 'We cannot send invites right now.';
                break;
            case 'EE031':
                $errorMsg = 'Your channel creation limit has been reached, please upgrade your plan to create more channels  .';
                break;
            case 'EE032':
                $errorMsg = 'Your daily limit for the broadcast has been reached, please upgrade your plan to create more broadcast';
                break;
            case 'EE033':
                $errorMsg = 'Plan id does not exist.';
                break;
            case 'EE034':
                $errorMsg = 'Email Id already exists.';
                break;
            case 'EE002':
                $errorMsg = 'Invalid password';
                break;
            case 'EE035':
                $errorMsg = 'Plan id cannot be empty';
                break;
            case 'EE036':
                $errorMsg = 'Old password does not match.';
                break;
            case 'EE037':
                $errorMsg = 'Required fields are missing.';
                break;
            case 'EE042':
                $errorMsg = 'Broadcast is either expired or start time is invalid.';
                break;
            case 'EE044':
                $errorMsg = 'Can not close broadcast.';
                break;
            case 'EE004':
                $errorMsg = 'Session has been expired';
                break;
            case 'EE038':
                $errorMsg = 'Content id does not exist.';
                break;
            case 'EE045':
                $errorMsg = 'Invalid request type';
                break;
            case 'EE046':
                $errorMsg = 'Required fields are missing.';
                break;
            case 'EE047':
                $errorMsg = 'No record found';
                break;
            case 'EE048':
                $errorMsg = 'Invalid mongoId';
                break;
            case 'EE049':
                $errorMsg = 'Invalid json';
                break;
            case 'EE050':
                $errorMsg = 'Invalid login type';
                break;
            default:
                $errorMsg = 'Error number ' . $errorNumber;
                break;
        }
        $error = array("errorCode" => $errorNumber, "errorMsg" => $errorMsg);
        array_push($errorData, $error);
        $mongoUserResult['errors'] = $errorData;
        return $mongoUserResult;
    }
}
?>