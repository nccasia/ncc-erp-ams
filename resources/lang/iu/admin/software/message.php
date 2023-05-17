<?php

return [

    'undeployable' 		=> '<strong>Warning: </strong> This Software has been marked as currently undeployable.
                        If this status has changed, please update the Software status.',
    'does_not_exist' 	=> 'Software does not exist.',
    'does_not_exist_or_not_requestable' => 'That Software does not exist or is not requestable.',
    'assoc_users'	 	=> 'This Software is currently checked out to a user and cannot be deleted. Please check the Software in first, and then try deleting again. ',

    'create' => [
        'error'   		=> 'Software was not created, please try again. :(',
        'success' 		=> 'Software created successfully. :)',
    ],

    'update' => [
        'error'   			=> 'Software was not updated, please try again',
        'success' 			=> 'Software updated successfully.',
        'nothing_updated'	=>  'No fields were selected, so nothing was updated.',
    ],

    'restore' => [
        'error'   		=> 'Software was not restored, please try again',
        'success' 		=> 'Software restored successfully.',
    ],

    'audit' => [
        'error'   		=> 'Software audit was unsuccessful. Please try again.',
        'success' 		=> 'Software audit successfully logged.',
    ],


    'deletefile' => [
        'error'   => 'File not deleted. Please try again.',
        'success' => 'File successfully deleted.',
    ],

    'upload' => [
        'error'   => 'File(s) not uploaded. Please try again.',
        'success' => 'File(s) successfully uploaded.',
        'nofiles' => 'You did not select any files for upload, or the file you are trying to upload is too large',
        'invalidfiles' => 'One or more of your files is too large or is a filetype that is not allowed. Allowed filetypes are png, gif, jpg, doc, docx, pdf, and txt.',
    ],

    'import' => [
        'error'                 => 'Some items did not import correctly.',
        'errorDetail'           => 'The following Items were not imported because of errors.',
        'success'               => 'Your file has been imported',
        'file_delete_success'   => 'Your file has been been successfully deleted',
        'file_delete_error'      => 'The file was unable to be deleted',
    ],


    'delete' => [
        'confirm'   	=> 'Are you sure you wish to delete this Software?',
        'error'   		=> 'There was an issue deleting the Software. Please try again.',
        'nothing_updated'   => 'No Softwares were selected, so nothing was deleted.',
        'success' 		=> 'The Software was deleted successfully.',
    ],

    'checkout' => [
        'error'   		=> 'Software was not checked out, please try again',
        'success' 		=> 'Software checked out successfully.',
        'user_does_not_exist' => 'That user is invalid. Please try again.',
        'not_available' => 'That Software is not available for checkout!',
        'no_Softwares_selected' => 'You must select at least one Software from the list',
    ],

    'checkin' => [
        'error'   		=> 'Software was not checked in, please try again',
        'success' 		=> 'Software checked in successfully.',
        'user_does_not_exist' => 'That user is invalid. Please try again.',
        'already_checked_in'  => 'That Software is already checked in.',

    ],

    'requests' => [
        'error'   		=> 'Software was not requested, please try again',
        'success' 		=> 'Software requested successfully.',
        'canceled'      => 'Checkout request successfully canceled',
    ],

];
