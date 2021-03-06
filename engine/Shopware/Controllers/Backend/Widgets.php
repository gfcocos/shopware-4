<?php
/**
 * Shopware 4
 * Copyright © shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Backend widget controller
 */
class Shopware_Controllers_Backend_Widgets extends Shopware_Controllers_Backend_ExtJs
{

    /**
     * Returns the list of active widgets for the current logged
     * in user as an JSON string.
     *
     * @public
     * @return void
     */
    public function getListAction()
    {
        $auth = Shopware()->Container()->get('auth');

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $identity = $auth->getIdentity();
        $userID = (int)$identity->id;

        $builder = Shopware()->Container()->get('models')->createQueryBuilder();
        $builder->select(array('widget', 'view'))
            ->from('Shopware\Models\Widget\Widget', 'widget')
            ->leftJoin('widget.views', 'view', 'WITH', 'view.authId = ?1')
            ->orderBy('view.position')
            ->setParameter(1, $userID);

        $data = $builder->getQuery()->getArrayResult();

        $widgets = array();
        foreach ($data as &$widgetData) {
            if (!$this->_isAllowed($widgetData['name'], 'widgets')) {
                continue;
            }

            $widgetData['label'] = Shopware()->Container()->get('snippets')->getNamespace('backend/widget/labels')
                ->get($widgetData['name'], $widgetData['label']);

            $widgets[] = $widgetData;
        }

        $this->View()->assign(array('success' => !empty($data), 'authId' => $userID, 'data' => $widgets));
    }

    /**
     * Sets the position for a single widget
     */
    public function savePositionAction()
    {
        $auth = Shopware()->Container()->get('auth');

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $request = $this->Request();
        $column = $request->getParam('column');
        $position = $request->getParam('position');
        $id = $request->getParam('id');

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        try {
            $this->setWidgetPosition($id, $position, $column);
        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
            return;
        }

        $this->View()->assign(array('success' => true, 'newPosition' => $position, 'newColumn' => $column));
    }

    /**
     * Sets the positions for all given widget ids
     */
    public function savePositionsAction()
    {
        $auth = Shopware()->Container()->get('auth');

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $widgets = $this->Request()->getParam('widgets');

        foreach ($widgets as $widget) {
            try {
                $this->setWidgetPosition($widget['id'], $widget['position'], $widget['column']);
            } catch (\Doctrine\ORM\ORMException $e) {
                $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
                return;
            }
        }

        $this->View()->assign(array('success' => true));
    }

    /**
     * Gets a widget by id and sets its column / row position
     *
     * @throws \Doctrine\ORM\ORMException
     * @param $viewId
     * @param $position
     * @param $column
     */
    private function setWidgetPosition($viewId, $position, $column)
    {
        $model = Shopware()->Container()->get('models')->find('Shopware\Models\Widget\View', $viewId);
        $model->setPosition($position);
        $model->setColumn($column);

        Shopware()->Container()->get('models')->persist($model);
        Shopware()->Container()->get('models')->flush();
    }

    /**
     * Creates a new widget for the active backend user.
     */
    public function addWidgetViewAction()
    {
        $auth = Shopware()->Container()->get('auth');

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $identity = $auth->getIdentity();
        $userID = (int)$identity->id;

        $request = $this->Request();
        $widgetId = $request->getParam('id');
        $column = $request->getParam('column');
        $position = $request->getParam('position');

        try {
            $model = new \Shopware\Models\Widget\View();
            $model->setWidget(
                Shopware()->Container()->get('models')->find('Shopware\Models\Widget\Widget', $widgetId)
            );
            $model->setAuth(
                Shopware()->Container()->get('models')->find('Shopware\Models\User\User', $userID)
            );
            $model->setColumn($column);
            $model->setPosition($position);

            Shopware()->Container()->get('models')->persist($model);
            Shopware()->Container()->get('models')->flush();

        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }

        $viewId = $model->getId();

        $this->View()->assign(array('success' => !empty($viewId), 'viewId' => $viewId));
    }

    /**
     * Removes active widgets by the passed views param
     */
    public function removeWidgetViewAction()
    {
        $auth = Shopware()->Container()->get('auth');

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $request = $this->Request();
        $id = $request->getParam('id');

        try {
            $model = Shopware()->Container()->get('models')->find('Shopware\Models\Widget\View', $id);
            Shopware()->Container()->get('models')->remove($model);
            Shopware()->Container()->get('models')->flush();

        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
            return;
        }

        $this->View()->assign(array('success' => true));
    }


    /**
     * Gets the turnover and visitors amount for the
     * chart and the grid in the "Turnover - Yesterday and today"-widget.
     *
     * @public
     * @return void
     */
    public function getTurnOverVisitorsAction()
    {
        // Get turnovers
        $fetchAmount = Shopware()->Container()->get('db')->fetchRow(
            "SELECT
                (
                    SELECT sum(invoice_amount/currencyFactor) AS amount
                    FROM s_order
                    WHERE TO_DAYS(ordertime) = TO_DAYS(now())
                    AND status != 4
                    AND status != -1
                ) AS today,
                (
                    SELECT sum(invoice_amount/currencyFactor) AS amount
                    FROM s_order
                    WHERE TO_DAYS(ordertime) = (TO_DAYS( NOW( ) )-1)
                    AND status != 4
                    AND status != -1
                ) AS yesterday
            "
        );

        if (empty($fetchAmount["today"])) {
            $fetchAmount["today"] = 0.00;
        }
        if (empty($fetchAmount["yesterday"])) {
            $fetchAmount["yesterday"] = 0.00;
        }

        $fetchAmount['today'] = round($fetchAmount['today'], 2);
        $fetchAmount['yesterday'] = round($fetchAmount['yesterday'], 2);

        // Get visitors
        $fetchVisitors = Shopware()->Container()->get('db')->fetchRow(
            "SELECT
                (
                    SELECT SUM(uniquevisits)
                    FROM s_statistics_visitors
                    WHERE datum = CURDATE()
                ) AS today,
                (
                    SELECT SUM(uniquevisits)
                    FROM s_statistics_visitors
                    WHERE datum = DATE_SUB(CURDATE(),INTERVAL 1 DAY)
                ) AS yesterday
        "
        );

        // Get new customers
        $fetchCustomers = Shopware()->Container()->get('db')->fetchRow(
            "SELECT
                (
                    SELECT COUNT(DISTINCT id)
                    FROM s_user
                    WHERE TO_DAYS( firstlogin ) = TO_DAYS( NOW( ) )
                ) AS today,
                (
                    SELECT COUNT(DISTINCT id)
                    FROM s_user
                    WHERE firstlogin = DATE_SUB(CURDATE(),INTERVAL 1 DAY)
                ) AS yesterday
        "
        );

        // Get order-count
        $fetchOrders = Shopware()->Container()->get('db')->fetchRow(
            "SELECT
                (
                    SELECT COUNT(DISTINCT id) AS orders
                    FROM s_order
                    WHERE TO_DAYS( ordertime ) = TO_DAYS( NOW( ) )
                    AND status != 4 AND status != -1
                ) AS today,
                (
                    SELECT COUNT(DISTINCT id) AS orders
                    FROM s_order
                    WHERE TO_DAYS(ordertime) = (TO_DAYS( NOW( ) )-1)
                    AND status != 4
                    AND status != -1
                ) AS yesterday
        "
        );


        if (empty($timeBack)) {
            $timeBack = 7;
        }

        $sql = "
        SELECT
            COUNT(id) AS `countOrders`,
            DATE_FORMAT(DATE_SUB(now(),INTERVAL ? DAY),'%d.%m.%Y') AS point,
            ((SELECT SUM(uniquevisits) FROM s_statistics_visitors WHERE datum >= DATE_SUB(now(),INTERVAL ? DAY) GROUP BY DATE_SUB(now(),INTERVAL ? DAY))) AS visitors
        FROM `s_order`
        WHERE
            ordertime >= DATE_SUB(now(),INTERVAL ? DAY)
        AND
            status != 4
        AND
            status != -1
        GROUP BY
            DATE_SUB(now(), INTERVAL ? DAY)
        ";
        $fetchConversion = Shopware()->Container()->get('db')->fetchRow(
            $sql,
            array($timeBack, $timeBack, $timeBack, $timeBack, $timeBack)
        );
        $fetchConversion = number_format($fetchConversion["countOrders"] / $fetchConversion["visitors"] * 100, 2);

        $namespace = Shopware()->Container()->get('snippets')->getNamespace('backend/widget/controller');
        $this->View()->assign(
            array(
                'success' => true,
                'data' => array(
                    array(
                        'name' => $namespace->get('today', 'Today'),
                        'turnover' => $fetchAmount["today"],
                        'visitors' => $fetchVisitors["today"],
                        'newCustomers' => $fetchCustomers["today"],
                        'orders' => $fetchOrders["today"]
                    ),
                    array(
                        'name' => $namespace->get('yesterday', 'Yesterday'),
                        'turnover' => $fetchAmount["yesterday"],
                        'visitors' => $fetchVisitors["yesterday"],
                        'newCustomers' => $fetchCustomers["yesterday"],
                        'orders' => $fetchOrders["yesterday"]
                    )
                ),
                'conversion' => $fetchConversion
            )
        );
    }

    /**
     * Gets the last visitors and customers for
     * the chart and the grid in the "Customers and visitors"-widget.
     *
     * @public
     * @return void
     */
    public function getVisitorsAction()
    {
        if (empty($timeBack)) {
            $timeBack = 8;
        }

        // Get visitors in defined time-range
        $sql = "
        SELECT datum AS `date`, SUM(uniquevisits) AS visitors
        FROM s_statistics_visitors
        WHERE datum >= DATE_SUB(now(),INTERVAL ? DAY)
        GROUP BY datum
        ";

        $data = Shopware()->Container()->get('db')->fetchAll($sql, array($timeBack));

        $result[] = array();
        foreach ($data as $row) {
            $result[] = array(
                "timestamp" => strtotime($row["date"]),
                "date" => date('d.m.Y', strtotime($row["date"])),
                "visitors" => $row["visitors"]
            );
        }

        // Get current users online
        $currentUsers = Shopware()->Container()->get('db')->fetchOne(
            "SELECT COUNT(DISTINCT remoteaddr) FROM s_statistics_currentusers WHERE time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)"
        );
        if (empty($currentUsers)) {
            $currentUsers = 0;
        }

        // Get current users logged in
        $fetchLoggedInUsers = Shopware()->Container()->get('db')->fetchAll(
            "
                    SELECT s.userID,
                    (SELECT SUM(quantity * price) AS amount FROM s_order_basket WHERE userID = s.userID GROUP BY sessionID ORDER BY id DESC LIMIT 1) AS amount,
                    (SELECT IF(ub.company,ub.company,CONCAT(ub.firstname,' ',ub.lastname)) FROM s_user_billingaddress AS ub WHERE ub.userID = s.userID) AS customer
                    FROM s_statistics_currentusers s
                    WHERE userID != 0
                    GROUP BY remoteaddr
                    ORDER BY amount DESC
                    LIMIT 6
                    "
        );

        foreach ($fetchLoggedInUsers as &$user) {
            $user["customer"] = htmlentities($user["customer"], null, "UTF-8");
        }

        $this->View()->assign(
            array(
                'success' => true,
                'data' => array(
                    'customers' => $fetchLoggedInUsers,
                    'visitors' => $result,
                    'currentUsers' => $currentUsers
                )
            )
        );
    }

    /**
     * Gets the latest orders for the "last orders" widget.
     *
     * @public
     * @return void
     */
    public function getLastOrdersAction()
    {
        $addSqlPayment = "";
        $addSqlSubshop = "";
        if (!empty($subshopID)) {
            $addSqlSubshop = "
            AND s_order.subshopID = " . Shopware()->Container()->get('db')->quote($subshopID);
        }

        if (!empty($restrictPayment)) {
            $addSqlPayment = "
            AND s_order.paymentID = " . Shopware()->Container()->get('db')->quote($restrictPayment);
        }

        $sql = "
        SELECT s_order.id AS id, currency,currencyFactor,firstname,lastname, company, subshopID, paymentID,  ordernumber AS orderNumber, transactionID, s_order.userID AS customerId, invoice_amount,invoice_shipping, ordertime AS `date`, status, cleared
        FROM s_order
        LEFT JOIN s_order_billingaddress ON s_order_billingaddress.userID = s_order.userID
        WHERE
            s_order.status != -1
        $addSqlSubshop
        $addSqlPayment
        AND
            ordertime >= DATE_SUB(now(),INTERVAL 14 DAY)
        GROUP BY s_order.id
        ORDER BY ordertime DESC
        LIMIT 20
        ";

        $result = Shopware()->Container()->get('db')->fetchAll($sql);
        foreach ($result as &$order) {
            $order["customer"] = htmlentities(
                $order["company"] ? $order["company"] : $order["firstname"] . " " . $order["lastname"],
                ENT_QUOTES,
                "UTF-8"
            );
            $amount = round(($order["invoice_amount"] / $order["currencyFactor"]), 2);
            $order["amount"] = $amount;
            if (strlen($order["customer"]) > 25) {
                $order["customer"] = substr($order["customer"], 0, 25) . "..";
            }
            unset($order["firstname"]);
            unset($order["lastname"]);
        }

        $this->View()->assign(
            array(
                'success' => true,
                'data' => $result
            )
        );
    }

    /**
     * Gets the saved notice from the database and
     * assigns it to the view-
     *
     * @public
     * @return void
     */
    public function getNoticeAction()
    {
        $userID = $_SESSION["Shopware"]["Auth"]->id;

        $noticeMsg = Shopware()->Container()->get('db')->fetchOne(
            "
                    SELECT notes FROM s_plugin_widgets_notes WHERE userID = ?
                    ",
            array($userID)
        );

        $this->View()->assign(array('success' => true, 'notice' => $noticeMsg));
    }

    /**
     * Saves the notice text from the notice widget.
     *
     * @public
     * @return void
     */
    public function saveNoticeAction()
    {
        $noticeMsg = (string)$this->Request()->getParam('notice');

        $userID = $_SESSION["Shopware"]["Auth"]->id;

        if (empty($userID)) {
            $this->View()->assign(array('success' => false, 'message' => 'No user id'));
            return;
        }
        if (Shopware()->Container()->get('db')->fetchOne("SELECT id FROM s_plugin_widgets_notes WHERE userID = ?", array($userID))) {
            // Update
            Shopware()->Container()->get('db')->query(
                "
                            UPDATE s_plugin_widgets_notes SET notes = ? WHERE userID = ?
                            ",
                array($noticeMsg, $userID)
            );
        } else {
            // Insert
            Shopware()->Container()->get('db')->query(
                "
                            INSERT INTO s_plugin_widgets_notes (userID, notes)
                            VALUES (?,?)
                            ",
                array($userID, $noticeMsg)
            );
        }
        $this->View()->assign(array('success' => true, 'message' => 'Successfully saved.'));
    }

    /**
     * Gets the last registered merchant for the "merchant unlock" widget.
     *
     * @public
     * @return void
     */
    public function getLastMerchantAction()
    {
        // Fetch all users that are pending approval
        $sql = "SELECT DISTINCT s_user.active AS active, customergroup,
            validation, email, s_core_customergroups.description AS customergroup_name,
            validation AS customergroup_id, s_user.id AS id, lastlogin AS date,
            company AS company_name, customernumber, CONCAT(firstname,' ',lastname) AS customer
        FROM s_user
        LEFT JOIN s_core_customergroups
            ON groupkey = validation,
        s_user_billingaddress
        WHERE
            s_user.id = s_user_billingaddress.userID
            AND validation != ''
            AND validation != '0'
        ORDER BY s_user.firstlogin DESC";

        $fetchUsersToUnlock = Shopware()->Container()->get('db')->fetchAll($sql);

        foreach ($fetchUsersToUnlock as &$user) {
            $user["customergroup_name"] = htmlentities($user["customergroup_name"], null, "UTF-8");
            $user["company_name"] = htmlentities($user["company_name"], null, "UTF-8");
            $user["customer"] = htmlentities($user["customer"], null, "UTF-8");
        }

        $this->View()->assign(array('success' => true, 'data' => $fetchUsersToUnlock));
    }

    /**
     * Creates the deny or allow mail from the db and assigns it to
     * the view.
     *
     * @public
     * @return bool
     */
    public function requestMerchantFormAction()
    {
        $customerGroup = (string) $this->Request()->getParam('customerGroup');
        $userId = (int) $this->Request()->getParam('id');
        $mode = (string) $this->Request()->getParam('mode');

        if ($mode === 'allow') {
            $tplMail = 'sCUSTOMERGROUP%sACCEPTED';
        } else {
            $tplMail = 'sCUSTOMERGROUP%sREJECTED';
        }
        $tplMail = sprintf($tplMail, $customerGroup);

        $builder = $this->container->get('models')->createQueryBuilder();
        $builder->select(array('customer.email', 'customer.languageId'))
            ->from('Shopware\Models\Customer\Customer', 'customer')
            ->where('customer.id = ?1')
            ->setParameter(1, $userId);

        $customer = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        if (empty($customer) || empty($customer['email'])) {
            $this->View()->assign(
                array(
                    'success' => false,
                    'message' => $this->container->get('snippets')->getNamespace('backend/widget/controller')
                            ->get('merchantNoUserId', 'There is no user for the specific user id')
                )
            );
            return false;
        }

        /** @var \Shopware\Models\Mail\Mail $mailModel */
        $mailModel = $this->getModelManager()->getRepository('Shopware\Models\Mail\Mail')->findOneBy(
            array('name' => $tplMail)
        );

        if (empty($mailModel)) {
            $this->View()->assign(
                array(
                    'success' => true,
                    'data' => array(
                        'content' => '',
                        'fromMail' => '{config name=mail}',
                        'fromName' => '{config name=shopName}',
                        'subject' => '',
                        'toMail' => $customer['email'],
                        'userId' => $userId,
                        'status' => ($mode === 'allow' ? 'accepted' : 'rejected')
                    )
                )
            );
            return true;
        }

        $translationReader = new Shopware_Components_Translation();
        $translation = $translationReader->read($customer['languageId'], 'config_mails', $mailModel->getId());
        $mailModel->setTranslation($translation);

        $mailData = array(
            'content' => nl2br($mailModel->getContent()) ? : '',
            'fromMail' => $mailModel->getFromMail() ? : '{config name=mail}',
            'fromName' => $mailModel->getFromName() ? : '{config name=shopName}',
            'subject' => $mailModel->getSubject(),
            'toMail' => $customer['email'],
            'userId' => $userId,
            'status' => ($mode === 'allow' ? 'accepted' : 'rejected')
        );
        $this->View()->assign(array('success' => true, 'data' => $mailData));
    }

    /**
     * Sends the mail to the merchant if the inquiry was
     * successful or was declined.
     *
     * @public
     * @return bool
     */
    public function sendMailToMerchantAction()
    {
        $params = $this->Request()->getParams();
        $mail = clone Shopware()->Container()->get('mail');

        $toMail = $params['toMail'];
        $fromName = $params['fromName'];
        $fromMail = $params['fromMail'];
        $subject = $params['subject'];
        $content = $params['content'];
        $userId = $params["userId"];
        $status = $params["status"];

        if (!$toMail || !$fromName || !$fromMail || !$subject || !$content || !$userId) {
            $this->View()->assign(array('success' => false, 'message' => 'All required fiels needs to be filled.'));
            return false;
        }

        $content = preg_replace('`<br(?: /)?>([\\n\\r])`', '$1', $params['content']);

        $compiler = new Shopware_Components_StringCompiler($this->View());
        $defaultContext = array(
            'sConfig' => Shopware()->Config(),
        );
        $compiler->setContext($defaultContext);

        // Send eMail to customer
        $mail->IsHTML(false);
        $mail->From = $compiler->compileString($fromMail);
        $mail->FromName = $compiler->compileString($fromName);
        $mail->Subject = $compiler->compileString($subject);
        $mail->Body = $compiler->compileString($content);
        $mail->ClearAddresses();
        $mail->AddAddress($toMail, "");

        if (!$mail->Send()) {
            $this->View()->assign(array('success' => false, 'message' => 'The mail could not be sent.'));
            return false;
        } else {
            if ($status == "accepted") {
                Shopware()->Container()->get('db')->query(
                    "
                                    UPDATE s_user SET customergroup = validation, validation = '' WHERE id = ?
                                    ",
                    array($userId)
                );
            } else {
                Shopware()->Container()->get('db')->query(
                    "
                                    UPDATE s_user SET validation = '' WHERE id = ?
                                    ",
                    array($userId)
                );
            }
        }
        $this->View()->assign(array('success' => true, 'message' => 'The mail was send successfully.'));
    }
}