<?php

return array(

    'does_not_exist' => 'Tool does not exist.',
    'user_does_not_exist' => 'User does not exist.',
    'not_found' => 'Tool not found',
    
    'create' => [
        'error'   => 'Tool was not created, please try again.',
        'success' => 'Tool created successfully.',
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
        'error'   => 'Tool was not updated, please try again',
        'success' => 'Tool updated successfully.',
    ],

    'delete' => [
        'confirm'   => 'Are you sure you wish to delete this tool?',
        'error'   => 'There was an issue deleting the tool. Please try again.',
        'success' => 'The tool was deleted successfully.',
    ],

    'checkout' => [
        'error'   => 'There was an issue checking out the tool. Please try again.',
        'success' => 'The tool was checkout successfully',
        'already_user' => " is already checkout to ",
        'not_available' => 'The tool is not available for checkout'
    ],

    'checkin' => [
        'error'   => 'There was an issue checking in the tool. Please try again.',
        'success' => 'The tool was checked in successfully',
        'not_available' => 'The tool is not available for checkin',
        'already_checked_in' => 'The tool is already checkin'
    ],

);
