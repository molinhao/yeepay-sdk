<?php

namespace App\zsahYeepayV2;

use YopClient3;
use YopRequest;
use YopSignUtils;

require_once("lib/YopClient3.php");
require_once("lib/Util/YopSignUtils.php");

class  Request
{

    public function __construct()
    {
        /// 配置文件
        include 'conf.php';
        ///父商编
        $this->parentMerchantNo = $parentMerchantNo;
        //收单子商户
        $this->merchantNo = $merchantNo;
        //子商户对称密钥,可调密钥获取接口获取,下单生成hmac使用
        $this->hmacKey = $hmacKey;
        //父商编私钥
        $this->private_key = $private_key;
        //易宝公钥
        $this->yop_public_key = $yop_public_key;
        //根地址
        $this->serverRoot = $serverRoot;
        $this->appKey = $appKey;
        $this->notifyUrl = $notifyUrl;
        $this->redirectUrl = $redirectUrl;

    }

    /*
     * 公共方法
     */
    public function toString($arraydata)
    {
        $Str = "";
        foreach ($arraydata as $k => $v) {
            $Str .= strlen($Str) == 0 ? "" : "&";
            $Str .= $k . "=" . $v;
        }
        return $Str;
    }

    /*
     * 取地址 -易宝官方方法
     */
    public function getUrl($response, $private_key)
    {
        $content = $this->toString($response);
        $sign = YopSignUtils::signRsa($content, $private_key);
        $url = $content . "&sign=" . $sign;
        return $url;
    }

    /*
     * 取数组 - 易宝官方方法
     */
    public function object_array($array)
    {
        if (is_object($array)) {
            $array = (array)$array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = $this->object_array($value);
            }
        }
        return $array;
    }

    /*
     *  签名$data: 提交的数据   ； $keys：  组合的字段
    */
    public function getHmac($data, $keys)
    {
        $hdata = array();
        $hdata['parentMerchantNo'] = $this->parentMerchantNo;
        $hdata['merchantNo'] = $this->merchantNo;
        foreach ($keys as $val) {
            if ($val) {
                if ($val == 'parentMerchantNo') {
                    $hdata[$val] = $this->parentMerchantNo;
                } elseif ($val == 'merchantNo') {
                    $hdata[$val] = $this->merchantNo;
                } else {
                    $hdata[$val] = $data[$val];
                }
            }
        }
        $hmacstr = hash_hmac('sha256', $this->toString($hdata), $this->hmacKey, true);
        $hmac = bin2hex($hmacstr);
        return $hmac;
    }

    /*
     * 取响应数据
    */
    public function getResponse($url, $request)
    {
        $response = YopClient3::post($url, $request);
        //取得返回结果
        $data = $this->object_array($response);
        return ['response' => $response];
    }

    /*
     * 组合数据
     * $data : 提交的数据  $keys：组合的字段   $hmac ： 签名的字段
     */
    public function params($data, $keys, $hmac = [])
    {
        $request = new YopRequest($this->appKey, $this->private_key, $this->serverRoot, $this->yop_public_key);

        foreach ($keys as $val) {
            if ($val && $val !== 'hmac') {
                if ($val == 'parentMerchantNo') {
                    $request->addParam("parentMerchantNo", $this->parentMerchantNo);
                    unset($data['parentMerchantNo']);
                } elseif ($val == 'merchantNo') {
                    $request->addParam("merchantNo", $this->merchantNo);
                    unset($data['merchantNo']);
                } elseif (key_exists($val, $data)) {
                    //stripslashes
                    $request->addParam($val, $data[$val]);
                }
            }
        }
        if (!empty($hmac)) {
            $request->addParam("hmac", $this->getHmac($data, $hmac));
        }
        return $request;
    }

    /*
       * 订单创建接口
     *   传入参数 ： orderId：商户请求号;  orderAmount：订单金额; timeoutExpress: 订单有效期	; timeoutExpressType:订单过期时间类型	; requestDate:请求时间	redirectUrl:页面通知地址	; notifyUrl:服务器通知地址	; assureType:资金到账类型	; assurePeriod:担保周期	; goodsParamExt:商品拓展信息	; paymentParamExt	:支付拓展信息	;industryParamExt:行业拓展参数	; riskParamExt:风控拓展参数	; memo: 自定义对账备注	; fundProcessType:资金处理类型	; divideDetail: 分账明细	;  csUrl: 清算回调地址	; divideNotifyUrl: 分账成功通知商户地址; timeoutNotifyUrl: 订单超时回告商户地址 ;
     *   响应参数  ： result： 响应结果	 =>  code： 返回码	； message： 返回信息描述	；parentMerchantNo： 平台商商户号	；merchantNo：子商户商户号	；orderId： 商户订单号	；uniqueOrderNo	： 易宝统一订单号	；goodsParamExt：商品拓展信息	； memo	： 自定义对账备注	；token： token；fundProcessType	： 资金处理类型
     * 详见： https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__order.html
     */

    public function sendOrder($data = [])
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'notifyUrl', 'orderId', 'orderAmount', 'timeoutExpress', 'requestDate', 'redirectUrl', 'goodsParamExt', 'paymentParamExt', 'industryParamExt', 'memo', 'riskParamExt', 'csUrl', 'fundProcessType', 'divideDetail', 'divideNotifyUrl'], ['orderId', 'orderAmount', 'notifyUrl']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/order', $request)];
    }

    /*
     * 订单查询接口
     * 传入参数 ： orderId： 商户请求号	； uniqueOrderNo： 统一订单号	；
     * 响应参数 ：result 响应结果	 =>  code: 返回码	; message: 返回信息	; bizSystemNo: 未命名	; parentMerchantNo	: 父商户编号	; merchantNo	: merchantNo	; orderId: 商户订单号	; uniqueOrderNo: 易宝统一订单号	;status: 订单状态	( PROCESSING：待支付CLOSE：订单关闭REJECT：订单拒绝TIME_OUT：订单过期SUCCESS：订单成功REPEALED：撤销完成REVOKED：订单取消 REVERSAL：交易冲正) ;orderAmount: 订单金额	; payAmount: 支付金额	; merchantFee: 商户手续费	; customerFee: 用户手续费	; requestDate: 请求时间	; paySuccessDate: 支付成功时间	; goodsParamExt	: 商品拓展信息	; memo: 自定义对账备注	; instCompany: 分期公司	; instNumber: 分期期数	; industryParamExt: 行业拓展参数	; cashierType: 收款场景	; paymentProduct: 支付产品	; token: token; bankId: 银行编码	; cardType: 付款人卡类型	; platformType: 平台类型字段	; payURL: 支付链接	; openID: 微信被扫返回的openID	; unionID: 微信被扫返回的unionID	; fundProcessType: 资金处理类型	; bizChannelId: 业务通道标识	; bankPaySuccessDate: 银行支付成功时间	; bankTrxId: 银行交易流水号	; haveAccounted:是否已入账	; accountPayMerchantNo:企业账户支付付款商商编 ; residualDivideAmount: 剩余可分账金额	; preauthStatus: 预授权状态	; preauthType: 预授权类型	; preauthAmount: 预授权冻结金额	; paymentSysNo	: 支付系统单号	; paymentStatus:支付状态	; divideRequestId: 分账请求号	; parentMerchantName: 父商户名称	; merchantName: 子商户名称	;
     * combPaymentDTO	 营销支付 =>  secondPayOrderNo: 第二支付订单号	; secondBankOrderNo: 第二银行订单号	; secondAmount	: 第二支付金额	; secondPaySuccessDate: 第二支付完成时间		.
     * cashFee :卡券现金支付金额	;settlementFee : 卡券应结算金额	; bankOrderId: 银行订单号	; bankCardNo: 银行卡号	; calFeeMerchantNo	: 计费商编	.
     * bankPromotionInfoDTOList 银行营销列表	; mobilePhoneNo : 手机号（前3后4)
     * 详见 ： https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__orderquery.html
     *
     */
    public function orderQuery($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'uniqueOrderNo', 'orderId', 'hmac'], ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/orderquery', $request)];
    }

    /*
     * 子商户入网—企业
        详见：https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__enterprisereginfoadd.html
     */
    public function enterpriseRegInfoAdd($data)
    {
        $request = $this->params($data, ['merFullName', 'merShortName', 'merCertNo', 'merCertType', 'legalName', 'legalIdCard', 'merContactName', 'merContactPhone', 'merContactEmail', 'merLevel1No', 'merLevel2No', 'merProvince', 'merCity', 'merDistrict', 'merAddress', 'taxRegistCert', 'accountLicense', 'orgCode', 'orgCodeExpiry', 'isOrgCodeLong', 'cardNo', 'headBankCode', 'bankCode', 'bankProvince', 'bankCity', 'productInfo', "fileInfo", "requestNo", "parentMerchantNo", "notifyUrl", "merAuthorizeType", "businessFunction", "signCallBackUrl"]);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/enterprisereginfoadd', $request)];
    }

    /*
     * 子商户入网—个体
     *  详见：https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__individualreginfoadd.html
     */
    public function individualRegInfoAdd($data)
    {
        $request = $this->params($data, ['merFullName', 'merShortName', 'merCertNo', 'legalName', 'legalIdCard', 'merLegalPhone', 'merLegalEmail', 'merLevel1No', 'merLevel2No', 'merProvince', 'merCity', 'merDistrict', 'merAddress', 'cardNo', 'headBankCode', 'bankCode', 'bankProvince', 'bankCity', 'productInfo', 'fileInfo', 'requestNo', 'parentMerchantNo', 'notifyUrl', 'merAuthorizeType', 'businessFunction', 'bankAccountType', 'signCallBackUrl']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/individualreginfoadd', $request)];
    }

    /*
     * 子商户入网—个人
     * 详见 ： https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__personreginfoadd.html
     */
    public function personRegInfoAdd($data)
    {
        $request = $this->params($data, ['requestNo', 'parentMerchantNo', 'legalName', 'legalIdCard', 'merLegalPhone', 'merLegalEmail', 'merLevel1No', 'merLevel2No', 'merProvince', 'merCity', 'merDistrict', 'merAddress', 'merScope', 'cardNo', 'headBankCode', 'bankCity', 'bankCode', 'bankProvince', 'productInfo', 'fileInfo', 'businessFunction', 'notifyUrl', 'merAuthorizeType', 'merShortName', 'signCallBackUrl']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/personreginfoadd', $request)];
    }

    /*
     * 查询银行卡卡bin信息
     * 请求参数 : bankCardNo
     * 响应参数: result 响应结果	=> returnMsg: 响应信息	; returnCode:响应Code; bankId: 总行编码	; bankCode: 发卡机构（POS收单行）编码; bankName: 银行名称	; cardName: 卡名	; cardLength: 卡号长度	; verifyLength: 发卡行标识长度	; verifyCode: 发卡行标识取值	; cardType: 卡类型	;
     * 详见  ： https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__query-bank-card-bin-info.html
     */
    public function queryBankCardBinInfo($data)
    {
        $request = $this->params($data, ['bankCardNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/query-bank-card-bin-info', $request)];
    }

    /*
     * 子商户入网一修改产品
     * 请求参数: merchantNo: 商户商编	; requestNo: 请求流水号 ; notifyUrl: 商户回调地址	; payProductInfo: 支付产品信息	; withdrawProductMap: withdrawProductMap	; smsCode: 短信验证码
     * 响应参数: result 响应结果 =>  returnMsg2: 返回信息	; returnCode: 返回码	; requestNo: 请求流水号	; merNo: 商户商编	; payProductRespMap	: 支付产品信息	; allSuccess: 是否全部开通成功
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__router__modify__pay-product-info.html
     */
    public function modifyPayProductInfo($data)
    {
        $request = $this->params($data, ['merchantNo', 'requestNo', 'notifyUrl', 'payProductInfo', 'withdrawProductMap', 'smsCode']);
        return ['response' => $this->getResponse('/rest/v1.0/router/modify/pay-product-info', $request)];
    }

    /*
     * 子商户入网一修改结算卡
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__merchantService__mer-settle__mer-settle-info-update-for-o2o.html
     */
    public function merSettleInfoUpdateForO2o($data)
    {
        $request = $this->params($data, ['merAuthorizeNum', 'bankcardNo', 'headBankName', 'bankName', 'bankProvince', 'bankCity', 'requestNo', 'merchantNo', 'callbackurl', 'withdrawType', 'unitId']);
        return ['response' => $this->getResponse('/rest/v1.0/merchantService/mer-settle/mer-settle-info-update-for-o2o', $request)];
    }

    /*
     * 子商户入网—查询支行信息
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__bankbranchinfo.html
     * 请求参数 :  headBankCode: 开户银行总行编码  （测试参数 BCCB）	; provinceCode	: 开户省 (测试参数 110000 )	; cityCode: 开户市 (测试参数 110000)
     * 响应参数: branchBankInfo: 支行信息	; returnMsg: 返回信息	; returnCode: 返回码
     */
    public function bankBranchInfo($data)
    {
        $request = $this->params($data, ['headBankCode', 'provinceCode', 'cityCode']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/bankbranchinfo', $request)];
    }

    /*
     * 开通产品查询
     * 请求参数: requestNo: 请求号	; merchantNo: 子商户编号	;
     * 响应参数: result 响应结果	=> returnMsg : 未命名	; returnCode: 未命名	; requestNo: 未命名	; merchantNo	: 未命名	;parentMerchantNo	: 未命名	; openProductInfo: 未命名
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__router__query__pay-product-info.html
     */
    public function payProductInfo($data)
    {
        $request = $this->params($data, ['merchantNo', 'requestNo']);
        return ['response' => $this->getResponse('/rest/v1.0/router/query/pay-product-info', $request)];
    }

    /*
     * 子商户入网—入网状态查询
     * 请求参数: yopFlag: yop请求标识	; merchantNo: 商户编号	; parentMerchantNo: 代理商编号
     * 响应参数: merNetInOutStatus: 入网状态对外	; requestNo: 入网请求号	; parentMerchantNo: 代理商编号	; externalId: 入网流水号	; merchantNo:商户编号	; returnMsg: 返回信息	; returnCode: 返回码	;
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__regstatusquery.html
     */
    public function regStatusQuery($data)
    {
        $request = $this->params($data, ['merchantNo', 'parentMerchantNo', 'yopFlag']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/regstatusquery', $request)];
    }

    /*
     * 子商户入网—协议查询
     * 请求参数: parentMerchantNo: 代理商编号	; merchantNo: 商户编号
     * 响应参数: agreementContent	: 协议信息	; requestNo: 入网请求号	; parentMerchantNo	: 代理商编号	; externalId:内部流水号	; merchantNo:商户编号	; returnMsg: 返回信息	; returnCode: 返回码
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__agreeinfoquery.html
     */
    public function agreeInfoQuery($data)
    {
        $request = $this->params($data, ['merchantNo', 'parentMerchantNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/agreeinfoquery', $request)];
    }

    /*
     * 子商户入网—重发授权码
     * 请求参数: phone: 手机号	; merchantNo: 商户编号	; parentMerchantNo	: 代理商编号
     * 响应参数: returnMsg : 返回描述信息	; returnCode: 返回描述码
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__sendauthorizenum.html
     */
    public function sendAuthorizeNum($data)
    {
        $request = $this->params($data, ['merchantNo', 'parentMerchantNo', 'phone']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/sendauthorizenum', $request)];
    }

    /*
     * 子商户入网一发送短验
     * 请求参数:  sourceType :类型	(必填，请传递固定值：NET_IN) ; parentMerchantNo : 代理商商编	; merchantNo: 子商户商编
     * 响应参数: result 响应结果	=>  returnMsg: 未命名	; returnCode: 未命名
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__merchantService__mer-out-invoke__send-mer-sms-notify.html
     */
    public function sendMerSmsNotify($data)
    {
        $request = $this->params($data, ['merchantNo', 'parentMerchantNo', 'sourceType']);
        return ['response' => $this->getResponse('/rest/v1.0/merchantService/mer-out-invoke/send-mer-sms-notify', $request)];
    }

    /*
     * 提现一一提现请求
     * 请求参数: customerNumber: 商户编号	; amount: 提现金额	; orderId: 商户订单号(必须15位数字)	; cashType:提现类型	( D1:D1提现    D0:D0提现) ; feeType: 计费类型	( 枚举：
       SOURCE：付款方手续费
       TARGET：收款方手续费
       商户承担：手续费从商户账户里扣除，出款金额即为实际打款金额。
       用户承担：手续费从出款金额中扣除，实际打款金额为出款金额扣除手续费后的金额。) ; leaveWord: 留言	; bankCardId: 预留参数	(预留参数，不传即可); notifyUrl: 回调地址
       响应参数: customerNumber: 商户编号	; groupNumber: 系统商编号	; amount: 提现金额	; orderId: 商户订单号	; cashType: 提现类型	; leaveWord: 留言	; errorCode: 错误码	; errorMsg: 错误信息	; feeType: 计费类型	;
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__balance__cash.html
     */
    public function cash($data)
    {
        $request = $this->params($data, ['customerNumber', 'amount', 'orderId', 'cashType', 'feeType', 'leaveWord', 'bankCardId', 'notifyUrl']);
        return ['response' => $this->getResponse('/rest/v1.0/balance/cash', $request)];
    }

    /*
     * 提现一一提现查询
     * 请求参数: customerNumber: 商户编号	; orderId: 商户订单号	; cashType: 提现类型	( 枚举：D1:D1提现  D0:D0提现)
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__balance__query-cash-byorder.html
     */
    public function queryCashByOrder($data)
    {
        $request = $this->params($data, ['customerNumber', 'orderId', 'cashType']);
        return ['response' => $this->getResponse('/rest/v1.0/balance/query-cash-byorder', $request)];
    }

    /*
     * 提现一一余额查询
     * 请求参数:  customerNumber: 商户编号
     * 响应参数: result 响应结果	=>  groupNumber: 系统商商编	; customerNumber: 商户编号	; errorCode: 错误码; errorMsg: 错误信息	; d1ValidAmount: 	d1可用余额; d0ValidAmount: d0可用余额
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__balance__account-query.html
     */
    public function accountQuery($data)
    {
        $request = $this->params($data, ['customerNumber']);
        return ['response' => $this->getResponse('/rest/v1.0/balance/account-query', $request)];
    }

    /*
     * 订单处理器——结算查询
     * 请求参数 : startSettleDate:结算开始时间;  endSettleDate	: 结算中止时间	;  settleMerchantNo: 结算商编
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__settlementsquery.html
     */
    public function settlementsQuery($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'startSettleDate', 'endSettleDate', 'hmac', 'settleMerchantNo'], ['parentMerchantNo', 'merchantNo', 'startSettleDate', 'endSettleDate']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/settlementsquery', $request)];
    }

    /*
     * 获取子商户密钥接口
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__hmackeyquery.html
     */
    public function hmackeyQuery($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/hmackeyquery', $request)];
    }

    /*
     * 订单处理器-分账
     * 请求参数: orderId: 商户订单号	; uniqueOrderNo: 统一订单号	; divideRequestId: 分账请求号	; contractNo: 合同号	; divideDetail: 分账明细	 ; isUnfreezeResidualAmount	 (样例 [{"ledgerNo":"10015004197","ledgerName":"测试","amount":"0.01"}]   )  : 是否解冻收单商户剩余可用金额(是否解冻收单商户剩余可用金额 可选TRUE、FALSE 默认TRUE  ); divideNotifyUrl:分账回调地址
     * 响应参数 : code: code; merchantNo: 子商编	; parentMerchantNo: 父商编	; message: message; bizSystemNo:bizSystemNo; orderId: 原订单商户订单号	; uniqueOrderNo: 易宝流水号	; divideRequestId:分账商户请求号	;status:status; divideDetail: 分账明细
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__divide.html
     */
    public function divide($data)
    {

        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo', 'divideRequestId', 'contractNo', 'divideDetail', 'isUnfreezeResidualAmount', 'hmac', 'divideNotifyUrl'], ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo', 'divideRequestId']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/divide', $request)];
    }

    /*
     * 订单处理器——资金全额确认
     * 请求参数: orderId : 商户订单号	 ;  uniqueOrderNo:  易宝统一订单号	;
     * 响应参数: unique_order_no: 易宝统一订单号	; divide_request_id	: divide_request_id	; code: code; message: message; biz_system_no: biz_system_no; parent_merchant_no:parent_merchant_no; merchant_no: merchant_no; order_id:order_id;
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__fullsettle.html
     */
    public function fullSettle($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo'], ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/fullsettle', $request)];
    }

    /*
     * 订单处理器-分账查询
     * 请求参数: parentMerchantNo: 父商编	; merchantNo: 子商编	; orderId: 商户订单号	; uniqueOrderNo	: 统一订单号	; divideRequestId: 分账请求号	;
     * 响应参数: code	: 返回码	; message: 返回信息	; status: 分账状态	; order_id	: 商户订单号	; merchant_no	: 子商户编号	; divide_detail: 分账详情	; unique_order_no	: 易宝统一订单号	; divide_detail: 分账详情	; biz_system_no: biz_system_no	 ; parent_merchant_no: 主商户编号	 ;
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__dividequery.html
     */
    public function divideQuery($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo', 'divideRequestId'], ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo', 'divideRequestId']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/dividequery', $request)];
    }

    /*
     * 订单处理器——退款查询
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__refundquery.html
     * 请求参数: orderId : 交易请求号	; refundRequestId: 退款请求号	; uniqueRefundNo: 统一退款号	;
     * 响应参数: result 响应结果	=>  code: 返回码	; message: 返回信息	; parentMerchantNo: 父商编; 	merchantNo:子商编	; orderId: 商户订单号	; refundRequestId: 商户退款请求号	; uniqueOrderNo:易宝订单号	; uniqueRefundNo: 易宝退款订单号	; refundAmount: 退款请求金额	; returnMerchantFee: 返还商户手续费	; returnCustomerFee: 返回用户手续费	; status: 退款状态	; description: 退款描述信息	; refundRequestDate: refundRequestDate;refundSuccessDate: 退款成功时间	; realDeductAmount: 实扣金额	; realRefundAmount: 实退金额	; accountDivided: 分账规则	 ;
     */
    public function refundQuery($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'orderId', 'refundRequestId', 'uniqueRefundNo'], ['parentMerchantNo', 'merchantNo', 'refundRequestId', 'orderId', 'uniqueRefundNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/refundquery', $request)];
    }

    /*
     * 订单处理器——退款请求
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__refund.html
     * 请求参数  orderId: 商户交易请求号	; refundRequestId: 退款请求号	; uniqueOrderNo: 统一订单号	; refundAmount: 退款金额	; accountDivided: 分账规则	([{"ledgerNo":"10000466921","amount":0.01,"ledgerName":"test@yeepay.com”},  {"ledgerNo":"10012426766","amount":0.01,"ledgerName":"test@yeepay.com"}]注意：收单方承担的金额也要写有JOSN串里，JSON里的金额总和等于退款金额) ;  description: 退款说明	; memo: 自定义对账备注	; notifyUrl: 退款结果回调商户地址 ;
     * 响应参数  result 响应结果	=> code : 返回码	; message: 返回信息描述	; bizSystemNo: 业务方标识	; parentMerchantNo	: 平台商商户号	; merchantNo: 子商户商户号	; orderId: 商户订单号	; refundRequestId: 商户退款请求号	; uniqueRefundNo: 易宝统一订单号	; status: 退款状态	; refundAmount: 退款金额	; residualAmount: 剩余金额	; description: 退款订单描述	; refundRequestDate: 退款请求日期	; accountDivided: 分账规则
     */
    public function refund($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'orderId', 'refundRequestId', 'uniqueOrderNo', 'refundAmount', 'accountDivided', 'description', 'memo', 'notifyUrl'], ['parentMerchantNo', 'merchantNo', 'orderId', 'orderId', 'uniqueOrderNo', 'refundRequestId', 'refundAmount']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/refund', $request)];
    }

    /*
     * 订单处理器——关闭订单
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__trade__orderclose.html
     * 请求参数  orderId : 订单请求号	; uniqueOrderNo: 统一订单号	; description: 关闭订单原因
     * 响应参数  result 响应结果	 =>   code: 返回码	; message : 返回信息	; bizSystemNo: 业务方标识; 	 parentMerchantNo:平台商商户号	; merchantNo: 子商户商户号	; orderId: 商户订单号	; orderCloseRequestId: 请求订单关闭号	;
     */
    public function orderClose($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo', 'description'], ['parentMerchantNo', 'merchantNo', 'orderId', 'uniqueOrderNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/trade/orderclose', $request)];
    }

    /*
     * API收银台聚合下单支付一体化
       https://open.yeepay.com/docs/retail000001/rest__v1.0__nccashierapi__api__orderpay.html
     *   请求参数  orderId :商户订单号	; orderAmount : 订单金额 (业务上是必须参数，单位:元  保留小数点后两位)	; timeoutExpress: 过期时间 (单位： 分钟， 默认 24 小时， 最小 1 分 钟， 最大 180 天)	; timeoutExpressType: 过期时间类型	( 订单有效期单位(DAY HOUR MINUTE SECOND) ; requestDate : 请求时间	( 请求时间， 用于计算订单有效期， 格式 yyyy-MM-dd HH:mm:ss， 不传默认为易宝接收到请求的时间 ) ; notifyUrl: 通知地址	; goodsParamExt: 商品拓展信息	( 业务上是必须参数， Json 格式， key 支持 goodsName （必填） 、 goodsDesc {"goodsName ":"商品名称","goodsDesc":"商品描述"}) ; industryParamExt	: 行业拓展参数	( 预留字段，暂时不需要输入) ;  riskParamExt: 风控拓展参数	; memo  : 自定义对账备注	; fundProcessType	: 资金处理类型	( 资金处理类型， 可选值：DELAY_SETTLE(“延迟结算”),REAL_TIME(“实时订单”);REAL_TIME_DIVIDE（” 实时 分账” ）SPLIT_ACCOUNT_IN(“实时拆分入账”);)  ; divideDetail : 分账明细	; csUrl: 清算回调地址	; divideNotifyUrl: 分账成功通知商户地址	; timeoutNotifyUrl	: 订单超时回告商户地址	; payTool : 支付工具	( SCCANPAY（用户扫码支付）MSCANPAY（商户扫码支付）WECHAT_OPENID（公众号支付）ZFB_SHH（支付宝生活号）MINI_PROGRAM（微信小程序）EWALLET（SDK支付)  ) ;payType: 支付方式	( 可选枚举：WECHAT：微信ALIPAY：支付宝UPOP:银联支付 , 此参数请根据商户在易宝开通的产品来确定传值，如果商户未开所传参数所对应的支付类型的话，接口会报错)  ; appId: 微信公众号支付使用	;  openId:  微信公众号/支付宝生活号支付使用	; merchantTerminalId: 设备号，用于被扫支付; payEmpowerNo: 授权码，用于被扫支付; merchantStoreNo: 门店编码，用于被扫支付	; userIp : 用户IP	; extParamMap: 扩展参数	;
     */
    public function apiOrderPay($data)
    {
        $request = $this->params($data, ['merchantNo', 'orderId', 'orderAmount', 'timeoutExpress', 'timeoutExpressType', 'requestDate', 'notifyUrl', 'goodsParamExt', 'industryParamExt', 'riskParamExt', 'memo', 'fundProcessType', 'divideDetail', 'csUrl', 'divideNotifyUrl', 'timeoutNotifyUrl', 'payTool', 'payType', 'appId', 'openId', 'merchantTerminalId', 'payEmpowerNo', 'merchantStoreNo', 'userIp', 'extParamMap'], ['parentMerchantNo', 'merchantNo', 'orderId', 'orderAmount', 'notifyUrl']);
        return ['response' => $this->getResponse('/rest/v1.0/nccashierapi/api/orderpay', $request)];
    }

    /*
     * 聚合报备——报备服务
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__router__open-pay-async-report__report.html
     * 请求参数 : callBackUrl: 回调地址	; channelCode: 业务通道编码	;  merchantName: 商户名称	; reportMerchantName: 报备商户名称	; reportMerchantAlias: 报备商户简称	; reportMerchantComment: 报备商户备注	; serviceTel: 客服电话	; contactName: 联系人名称	; contactPhone: 联系电话	; contactMobile: 联系人手机	; contactEmail: 联系人邮箱	; institutionCode: 组织机构编码	; merchantAddress: 商户地址	;merchantProvince: 商户所在地省份	; merchantCity: 商户所在地市	; merchantDistrict: 商户所在地区县	; merchantLicenseNo: 商户营业执照号	; corporateIdCardNo: 企业法人身份ID卡号	; contactType: 联系人类型	; reportInfosJsonStr: 报备附加参数	; reportFeeType:报备费率类型	; promotionType: 促销类型	;
     * 响应参数: result 响应结果	=> traceId: 路由跟踪号	; dealStatus: 处理结果	; bizCode: 业务返回码	 ; bizMsg: 业务返回 信息
     */

    public function openPayAsyncReportReport($data)
    {
        $request = $this->params($data, ['merchantNo', 'channelNo', 'callBackUrl', 'channelCode', 'merchantName', 'reportMerchantName', 'reportMerchantAlias', 'reportMerchantComment', 'serviceTel', 'contactName', 'contactPhone', 'contactMobile', 'contactEmail', 'institutionCode', 'merchantAddress', 'merchantProvince', 'merchantCity', 'merchantDistrict', 'merchantLicenseNo', 'corporateIdCardNo', 'contactType', 'reportInfosJsonStr', 'reportFeeType', 'promotionType']);
        return ['response' => $this->getResponse('/rest/v1.0/router/open-pay-async-report/report', $request)];
    }

    /*
     * 聚合报备一一报备查询
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__router__open-pay-report__query.html
     * 请求参数: sceneType: scene_type ( 场景类型ACTIVE：主扫PASSIVE：被扫、H5：H5支付JSAPI：公众号SDK：SDK支付LN：生活号JSPAY：银联JS支付（类似公众号）)	 ; reportId : 粉丝路由重复进件标识符;
     * 响应参数 :  result  响应结果	 =>  traceId: 路由跟踪号	; dealStatus: 结果; bizCode: 业务返回码	; bizMsg: 业务返回信息	; openPayReportDTOList  查询结果列表	 =>merchantNo: 易宝商户号	; reportId: 粉丝路由重复进件的标识符	; reportMerchantNo: 未命名	; channelNo: channelNo; channelName: 业务通道名称	; channelCode: 业务通道编码	; sceneType: 场景类型	; appId: appId; appSecret: app秘钥	; status: 报备记录状态开关	; reportMsg: 报备状态信息	; reportStatusCode: 报备状态编码	; bankType: 通道类型	'; lineType: 使用场景	; reportFee: 未命名	; promotionType: 未命名	; bankCode: 未命名	; dealStatus: 未命名	; errMsg: 未命名	;
     */
    public function openPayReportQuery($data)
    {
        $request = $this->params($data, ['merchantNo', 'sceneType', 'reportId']);
        return ['response' => $this->getResponse('/rest/v1.0/router/open-pay-report/query', $request)];
    }

    /*
     * 统一公众号配置
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__router__open-pay-async-report__config.html
     * 请求参数:  appId: APP ID; tradeAuthorizationDirectories: 交易授权目录(一次可以配置多个，多个之间以"#"隔开
如地址中有"#"，将"#"进行urlcode后提交如需要使用亿企通标准收银台来完成支付，请在配置以下目录：https://cash.yeepay.com/newwap/)	; wxpMerAppId: 推荐关注的公众号	; senceType: 场景 (默认为公众号MINI_PROGRAM：小程序WECHAT_OPENID：公众号)  ; callBackUrl: 配置回调地址	; channelNo: 渠道号	;  promotionTypeEnum: 促销类型 (Normal：普通通道 GreenIsland：绿洲)	; channelIds: 配置渠道号集合(数组类型，可指定配置传入的渠道号进行公众号/小程序的配置) ;
     */
    public function openPayAsyncReportConfig($data)
    {
        $request = $this->params($data, ['merchantNo', 'appId', 'tradeAuthorizationDirectories', 'wxpMerAppId', 'senceType', 'callBackUrl', 'channelNo', 'promotionTypeEnum', 'channelIds']);
        return ['response' => $this->getResponse('/rest/v1.0/router/open-pay-async-report/config', $request)];
    }

    /*
     * 统一公众号配置查询
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__router__open-pay-jsapi-config__query.html
     * 请求参数:  appId : appId	; channelIds: 渠道号集合	; senceType: 场景
     * 响应参数 : traceId: 路由跟踪号	; dealStatus: 处理结果(0:失败 1:成功)	; bizCode: 业务返回编码	; bizMsg: 业务返回信息	; jsapiConfigDTOList	: 公众号配置查询结果列表	 ( 公众号配置查询结果列表，单条结果的字段) ;
     */
    public function openPayAsyncReportQuery($data)
    {
        $request = $this->params($data, ['merchantNo', 'appId', 'channelIds', 'senceType']);
        return ['response' => $this->getResponse('/rest/v1.0/router/open-pay-jsapi-config/query', $request)];
    }

    /*
     * openid查询
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__new-retail__marketing__query-open-id.html
     *请求参数 : merNo: 商户编号	; appId: appId; authCode: 支付授权码	; requestNo: 请求ID
     * 响应参数  result 响应结果	 =>bizCode : 返回码	; bizMsg: 返回信息	; traceId: 易宝流水号	; requestNo: 商户请求号	 ; dealStatus: 返回结果	; sub_openid	: openid ;
     */
    public function queryOpenId($data)
    {
        $request = $this->params($data, ['merNo', 'appId', 'authCode', 'requestNo']);
        return ['response' => $this->getResponse('/rest/v1.0/new-retail/marketing/query-open-id', $request)];
    }

    /*
     * 获取商户余额接口
     * https://open.yeepay.com/docs/retail000001/rest__v1.0__sys__merchant__balancequery.html
     * 请求参数  parentMerchantNo: 代理商编号	 ; merchantNo: 商户编号
     * 响应参数  returnCode: 返回描述码	; merBalance: 商户余额	 ;  returnMsg:  返回描述信息
     */
    public function balanceQuery($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo']);
        return ['response' => $this->getResponse('/rest/v1.0/sys/merchant/balancequery', $request)];
    }

    /*
     * 转账接口
     * https://open.yeepay.com/docs/enterprise0001/5bd1a0d4441ae6005d160b95.html
     * 请求参数 : requestNo: 转账请求号; creditCustomerNo: 转入方商编	; amount: 转账金额	; usage: 转账用途
     * 同步响应参数 requestNo	: 转账请求号	; amount: 转账金额 ( 示例: 0.01 )	; creditCustomerNo: 转入方商编	; usage: 转账用途	; fee: 手续费	; createTime	: 创建时间	; trxTime	: 转入成功时间	; status: 状态	 ;
     */
    public function transfer($data)
    {
        $request = $this->params($data, ['requestNo', 'creditCustomerNo', 'amount', 'usage']);
        return ['response' => $this->getResponse('/rest/v1.0/cloud-account/account-transfer/transfer', $request)];
    }

    /*
     * 转账查询接口
     * https://open.yeepay.com/docs/enterprise0001/5bd1a3d72676f60063f0a69b.html
     * 请求参数 : requestNo: 转账请求号	;
     * 同步响应参数 : requestNo: 转账请求号	;  amount: 转账金额	; creditCustomerNo: 转入方商编	; usage: 转账用途	; fee: 手续费	; createTime: 创建时间	; trxTime: 转入成功时间	; status: 状态	( 枚举PROCESSING：处理SUCCESS:成功 FAIL:失败)
     */
    public function queryTransfer($data)
    {
        $request = $this->params($data, ['requestNo']);
        return ['response' => $this->getResponse('/rest/v1.0/cloud-account/account-transfer/query-transfer', $request)];
    }

    /*
     * 交易对账接口
     * https://open.yeepay.com/docs/retail000001/5be17515c3eaf0005c023be2.html
     * 请求参数   dayString	 :  对账日期	(2018-10-22) ;
     * 同步响应参数
     *  "账单日期","2018-08-18","商户编号","1001850****"
        "交易总金额","1944****.74","退款总金额","0.00","撤销总金额","0.00"
        "交易总笔数","311","退款总笔数","0","撤销总笔数","0"
        "交易手续费金额","0.00","退款退回手续费金额","0.00","撤销退回手续费金额","0.00"
        "交易时间","记账时间","商户订单号","业务类型","订单金额","手续费","产品类型","支付方式","对账备注","易宝流水号"
        "2018-08-17 23:55:51","2018-08-18 00:02:30","201808171****4529","交易","1300.00","0.00","WEB收银台","网银B2C","","10012018081700000****01021"
        "2018-08-18 00:06:47","2018-08-18 00:08:06","201808181****086","交易","5000.00","0.00","WEB收银台","网银B2C","","100120180818000000****3920"
        "2018-08-18 00:08:07","2018-08-18 00:09:03","20180818****6200","交易","12550.00","0.00","WEB收银台","网银B2C","","1001201808180000000****50"
        "2018-08-18 00:17:07","2018-08-18 00:18:10","2018081815****34","交易","10090.00","0.00","WEB收银台","网银B2C","","100120180818000000015****32"
     */
    public function tradedayDownload($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'dayString']);
        return ['response' => $this->getResponse('/yos/v1.0/std/bill/tradedaydownload', $request)];
    }
    /*
     * 分账对账接口
     * https://open.yeepay.com/docs/retail000001/5be1752e9dccec005c09f2e1.html
     * 请求参数 :  dayString :对账日期
     * 同步响应参数
     * "账单日期","2019-03-13","商户编号","10017521601"
        "分账总金额","9975.00","分账退款总金额","0.00"
        "分账总笔数","2","分账退款总笔数","0"
        "下单时间","完成时间","商户订单号","订单金额","订单状态","分账流水号","分账金额","分账商户编号","分账名称","分账时间","分账类型"
        "2019-03-13 12:09:41","2019-03-13 12:09:58","BYJZF799838840646946816","4985.00","SUCCESS","1008201903130000000519668206","-5.48","10014221185","易宝科技","2019-03-13 12:09:41","分账"
        "2019-03-13 12:10:59","2019-03-13 12:11:11","BYJZF799839167344857088","4990.00","SUCCESS","1008201903130000000519675053","-5.49","10014221185","易宝科技","2019-03-13 12:10:59","分账"
     */
    /*
     * 按日对帐
     */
    public function dividedayDownloadByDay($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'dayString']);
        return ['response' => $this->getResponse('/yos/v1.0/std/bill/dividedaydownload', $request)];
    }

    /*
     * 按月对帐
     */
    public function dividedayDownloadByMonth($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'monthString']);
        return ['response' => $this->getResponse('/yos/v1.0/std/bill/dividedaydownload', $request)];
    }

    /*
     * 其他对账接口  提现日对账
     * https://open.yeepay.com/docs/retail000001/5be175519dccec005c09f2e2.html
     * 请求参数  dayString: 对账日期	(2018-10-22) ;  dataType	: 数据类型	(  jy：按交易流水对账  zt：按状态对账  );
     * 对账文件示例
     * "账单日期","2018-08-18","商户编号","1001850****"
        "交易总金额","1944****.74","退款总金额","0.00","撤销总金额","0.00"
        "交易总笔数","311","退款总笔数","0","撤销总笔数","0"
        "交易手续费金额","0.00","退款退回手续费金额","0.00","撤销退回手续费金额","0.00"
        "交易时间","记账时间","商户订单号","业务类型","订单金额","手续费","产品类型","支付方式","对账备注","易宝流水号"
        "2018-08-17 23:55:51","2018-08-18 00:02:30","201808171****4529","交易","1300.00","0.00","WEB收银台","网银B2C","","10012018081700000****01021"
        "2018-08-18 00:06:47","2018-08-18 00:08:06","201808181****086","交易","5000.00","0.00","WEB收银台","网银B2C","","100120180818000000****3920"
        "2018-08-18 00:08:07","2018-08-18 00:09:03","20180818****6200","交易","12550.00","0.00","WEB收银台","网银B2C","","1001201808180000000****50"
        "2018-08-18 00:17:07","2018-08-18 00:18:10","2018081815****34","交易","10090.00","0.00","WEB收银台","网银B2C","","100120180818000000015****32"
     */
    public function cashdayDownload($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'dayString', 'dataType']);
        return ['response' => $this->getResponse('/yos/v1.0/std/bill/cashdaydownload', $request)];
    }

    /*
     *其他对账接口  出款日对账
     * https://open.yeepay.com/docs/retail000001/5be175519dccec005c09f2e2.html
     * 请求参数  dayString: 对账日期   ( 例如  2018-10-22)	;  dataType	: 数据类型	 (   trade：所有状态交易 success：成功交易)
     */
    public function remitdayDownload($data)
    {
        $request = $this->params($data, ['parentMerchantNo', 'merchantNo', 'dayString', 'dataType']);
        return ['response' => $this->getResponse('/yos/v1.0/std/bill/remitdaydownload', $request)];
    }

}
