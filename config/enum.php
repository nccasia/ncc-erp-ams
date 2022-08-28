<?php

return [
  "request_status" => [
      "PENDING" => "Pending",
      "SENT" => "Sent",
      "APPROVED" => "Approved"
  ],
  "assigned_status" => [
    "DEFAULT" => 0, 
    "WAITING" => 1, 
    "ACCEPT"  => 2,  
    "REJECT"  => 3,
    "WAITINGCHECKOUT" => 4, 
    "WAITINGCHECKIN" => 5, 
    "ACCEPTCHECKOUT" => 6, 
    "ACCEPTCHECKIN" => 7,
    "REJECTCHECKOUT"  => 8,
    "REJECTCHECKIN"  => 9,
  ],
  "status_id" => [
    "PENDING" => 1,
    "BROKEN" => 3,
    "ASSIGN" => 4,
    "READY_TO_DEPLOY" => 5,
    "CHECKIN" => 6,
  ],
  "asset_history" => [
    "CHECK_IN_TYPE" => 1,
    "CHECK_OUT_TYPE" => 0
  ]
];