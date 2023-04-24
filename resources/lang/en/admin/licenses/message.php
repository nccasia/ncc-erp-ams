<?php

return array(

    'does_not_exist' => 'License does not exist.',
    'user_does_not_exist' => 'User does not exist.',
    'asset_does_not_exist' 	=> 'The asset you are trying to associate with this license does not exist.',
    'owner_doesnt_match_asset' => 'The asset you are trying to associate with this license is owned by somene other than the person selected in the assigned to dropdown.',
    'assoc_users'	 => 'This license is currently checked out to a user and cannot be deleted. Please check the license in first, and then try deleting again. ',
    'select_asset_or_person' => 'You must select an asset or a user, but not both.',
    'not_found' => 'License not found',

    'create' => [
        'error'   => 'License was not created, please try again.',
        'success' => 'License created successfully.',
        'expiration_date' => 'Expiration date cannot be less than purchase date'
    ],

    'deletefile' => [
        'error'   => 'File not deleted. Please try again.',
        'success' => 'File successfully deleted.',
    ],

    'upload' => [
        'error'   => 'File(s) not uploaded. Please try again.',
        'success' => 'File(s) successfully uploaded.',
        'nofiles' => 'You did not select any files for upload, or the file you are trying to upload is too large',
        'invalidfiles' => 'One or more of your files is too large or is a filetype that is not allowed. Allowed filetypes are png, gif, jpg, jpeg, doc, docx, pdf, txt, zip, rar, rtf, xml, and lic.',
    ],

    'update' => [
        'error'   => 'License was not updated, please try again',
        'success' => 'License updated successfully.',
    ],

    'delete' => [
        'confirm'   => 'Are you sure you wish to delete this license?',
        'error'   => 'There was an issue deleting the license. Please try again.',
        'success' => 'The license was deleted successfully.',
    ],

    'checkout' => [
        'error'   => 'There was an issue checking out the license. Please try again.',
        'success' => 'The license was checked out successfully',
        'not_available'=> 'The license was not available',
        'user_not_available' => 'Already checkout key to '
    ],

    'checkin' => [
        'error'   => 'There was an issue checking in the license. Please try again.',
        'success' => 'The license was checked in successfully',
    ],

);
