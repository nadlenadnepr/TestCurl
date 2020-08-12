<?php
    //предполагаем, что данные в $data не нужно проверять на валидность (это могло быть сделано на уровне формы)
    $url = 'url/api';
    function myCurlRequest($data, $type = 'json', $url){
        $requestData = Array();
        $requestArray = Array();
        $inFields  = Array('firstName','lastName','dateOfBirth','Salary','creditScore');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        switch ($type) {
            case 'json':
                //готовим данные
                $outFields  = Array('firstName','lastName','dateOfBirth','Salary','creditScore');
                foreach ($data as $k=>$v){
                    if(in_array($k,$inFields)){
                        $requestData[$outFields[array_search($k,$inFields)]]=$v;
                    }
                }
				$requestArray = json_encode(array("userInfo"=>$requestData));
                curl_setopt($ch, CURLOPT_HTTPHEADER,
                    Array(
                        'Content-Type:application/json',
                        'Content-Length: ' . strlen($requestArray)
                    ));
                break;
            case 'xml':
                //готовим данные
                $outFields = Array('firstName','lastName','age','salary','creditScore');
                foreach ($data as $k=>$v){
                    if(in_array($k,$inFields)){
                        if($k=='dateOfBirth'){
							$v = AgeCalc($v);
                        }
                        $requestData[$outFields[array_search($k,$inFields)]]=$v;
                    }
                }
				$requestArray = '<?xml version="1.0"?>
					<userInfo version="1.6">';
				foreach($requestData as $k=>$v){
					$requestArray .= '<'.$k.'>'.$v.'</'.$k.'>';
				}
				$requestArray .= '</userInfo>';
				
                curl_setopt($ch, CURLOPT_HTTPHEADER,
                    Array(
                        'Content-Type: text/xml',
                        'Content-Length: ' . strlen($requestArray)
                    ));
                break;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestArray);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data_result = curl_exec($ch);
        curl_close($ch);
		
		//разбираем ответ
		switch ($type) {
			case 'json':
				$resultArray = json_decode($data_result,1);
				$statusResult = getStatusJson($resultArray);
				break;
			case 'xml':
				$statusResult = getStatusXML($data_result);
				break;
			default:
				$statusResult = Array(0,'Unknown');
				break;
		}
		return $statusResult;
    }

    function creditScoreMap($creditScore){
        $result = '';
        switch ($creditScore){
            case 'good':
                $result = 700;
                break;
            case 'bad':
                $result = 300;
                break;
        }
        return $result;
    }

    function AgeCalc($birthday){
        $datetime = new DateTime($birthday);
        $interval = $datetime->diff(new DateTime(date("Y-m-d")));
        return $interval->format("%Y");
    }
	
	function getStatusJson($array){
		$result = $array['SubmitDataResult'];

		switch ($result) {
			case 'success':
				$status = Array(1,'Sold');
				break;
			case 'reject':
				$status = Array(0,'Reject');
				break;
			case 'Error':
				$status = Array(0,'error',$array['SubmitDataErrorMessage']);
				break;
			default:
				$status = Array(0,'Unknown');
		}
		return $status;
	}
	
	function getStatusXML($xml){
		$dom = new DomDocument();
		$dom->loadXML($xml);
		$root = $dom->documentElement;

		foreach ($root->childNodes AS $item) {
			if($item->nodeName == 'returnCodeDescription'){
				$result = $item->nodeValue;
			}
			if($item->nodeName == 'transactionId'){
				$trID = $item->nodeValue;
			}
			if($item->nodeName == 'returnError'){
				$msg = $item->nodeValue;
			}
		}

		switch ($result) {
			case 'SUCCESS':
				$status = Array(1,'Sold',$trID);
				break;
			case 'REJECT':
				$status = Array(0,'Reject');
				break;
			case 'ERROR':
				$status = Array(0,'Error',$msg);
				break;
			default:
				$status = Array(0,'Unknown');
				break;
		}
		return $status;
	}