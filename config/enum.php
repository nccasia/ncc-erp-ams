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
    "REJECTREVOKE"  => 6,
    "WAITINGCHECKOUT" => 4, 
    "WAITINGCHECKIN" => 5, 
  ],
  "status_id" => [
    "READY_TO_DEPLOY" => 1,
    "PENDING" => 2,
    "ARCHIVED" => 3,
    "OUT_FOR_DIAGNOSTICS" => 4,
    "OUT_FOR_REPAIR" => 5,
    "BROKEN_NOT_FIXABLE" => 6,
    "LOST_STOLEN" => 7,
    "ASSIGN" => 8,
  ],
  "asset_history" => [
    "CHECK_IN_TYPE" => 1,
    "CHECK_OUT_TYPE" => 0
  ],
  "seats" =>[
    "MIN" => 0,
  ]
];