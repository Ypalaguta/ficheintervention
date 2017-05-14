<?php

class PluginFicheinterventionFicheIntervention extends CommonDBTM
{

    var $fields = array();

    public static function getTypeName($nb = 1)
    {
        return _n("Intervention sheet", "Intervention sheets", $nb, 'ficheintervention');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::getTypeName(2);
    }

    /**
     * Return the last generation date
     * @param int $ticket_id
     * @param int $task_id
     * @return string
     */
    static function getLastGenerationDate($ticket_id, $task_id)
    {

        $str = __("Task generation", 'ficheintervention') . " #" . $task_id;

        $log = new Log();
        $logfound = $log->find("itemtype = 'Ticket' AND 
            items_id = $ticket_id AND 
            itemtype_link = '" . __CLASS__ . "' AND 
            new_value = '$str'"
            , "date_mod desc", "1");

        if (empty($logfound)) {
            return NOT_AVAILABLE;
        }

        $found = array_shift($logfound);
        return $found["date_mod"];
    }

    static function showForm($tag)
    {
        global $DB, $CFG_GLPI;

        $instID = $tag->fields['id'];

        $canedit = $tag->can($instID, CREATE);

        if ($canedit) {
            echo "<div class='firstbloc'>";
            echo "<form name='ficheintervention_form' id='ficheintervention_form' method='post' target='_blank' 
         action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";

            echo "<table class='tab_cadre_fixe'>";
            //echo "<tr class='tab_bg_2'><th colspan='7'>".__("Task list", "ficheintervention")."</th></tr>";

            $tickettask = new TicketTask();
            $tasks_found = $tickettask->find("tickets_id=" . $instID);

            echo "<tr>
                  <th width='10'></th>
                  <th>" . __("Name") . ' - ' . __("ID") . "</th>
                  <th>" . __("Category") . "</th>
                  <th>" . __("Begin date") . "</th>
                  <th>" . __("End date") . "</th>
                  <th>" . __("Technician concerned", 'ficheintervention') . "</th>
                  <th>" . __("Date of last generation", 'ficheintervention') . "</th>
               </tr>";
            foreach ($tasks_found as $task) {
                $task_id = $task['id'];
                $task_content = $task['content'] . " (" . __('Task') . ' ' . $task_id . ')';

                $taskcategory = new TaskCategory();
                $taskcategory->getFromDB($task["taskcategories_id"]);

                echo "<tr class='tab_bg_1'>";
                echo "<td width='10'><input type='checkbox' name='task_id[$task_id]' value='true' /></td>";
                echo "<td class='center b'>$task_content</td>";
                echo "<td class='center'>" . $taskcategory->getField("name") . "</td>";
                echo "<td class='center'><span title='" . $task["date"] . "'>" . Html::convDate($task["date"]) . "</span></td>";
                echo "<td class='center'><span title='" . $task["end"] . "'>" . Html::convDate($task["end"]) . "</span></td>";
                echo "<td class='center'>" . getUserName($task["users_id_tech"]) . "</td>";
                echo "<td class='center'>";
                $date_last_generation = self::getLastGenerationDate($instID, $task_id);

                if ($date_last_generation == NOT_AVAILABLE) {
                    echo self::getTypeName(1) . ' ' . __('never generated', 'ficheintervention');
                } else {
                    echo sprintf(__('generated on %s'), Html::convDateTime($date_last_generation));
                }
                echo "</td>";
                echo "</tr>";
            }

            echo "</td><td class='center' colspan='7'>";
            echo "<input type='hidden' name='ticket_id' value='$instID'>";
            if (count($tasks_found) != 0) {
                echo "<br><input type='submit' name='generate' 
                  onclick='setTimeout(function () { window.location.reload(); }, 10)' 
                  value=\"" . __("Generate the intervention sheet", 'ficheintervention') . "\" class='submit'>";
            }
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }
    }

    /**
     * Define content of tab
     **/
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $plugin = new self();
        $plugin->showForm($item);
        return true;
    }

    function pregenerateInterventionSheet($id_ticket, $task_id)
    {

        $this->initFields();
        $this->getBodyField($id_ticket, $task_id);
        $this->getFooterFields();

        //Add a line in ticket history, with task ID
        $changes = array("0", "", __("Task generation", 'ficheintervention') . " #" . $task_id);
        Log::history($id_ticket, "Ticket", $changes, __CLASS__, Log::HISTORY_ADD_SUBITEM);
    }

    function initFields()
    {
        $tab = array("ticket", "technician", "descriptionMerged", "requester", "origin", "requesterName", "requesterPhone", "customer", "num_contrat", "model", "ID_equipment", "serial",
            "partnumber", "place", "ref:building", "ref:room", "comment", "title_ticket", "description", "serial", "targetItemLocation",
            // ** Footer ** :
            "company_name", "addr", "addr:city", "addr:country", "phone", "phone:mobile", "nameAsset", "idAsset");

        foreach ($tab as $key) {
            $this->fields[$key] = '';
        }
    }

    function convDate($time)
    {

        if (is_null($time) || ($time == 'NULL')) {
            return NULL;
        }

        // DD-MM-YYYY
        $date = substr($time, 8, 2) . "/";  // day
        $date .= substr($time, 5, 2) . "/"; // month
        $date .= substr($time, 0, 4);     // year
        return $date;
    }

    /**
     *
     * @param unknown $id_ticket
     * @param unknown $id_task
     * @return array
     */
    function getBodyField($id_ticket, $id_task)
    {
        global $DB;
        $ticket = new Ticket();
        $ticket->getFromDB($id_ticket);

        $task = new TicketTask();
        $task->getFromDB($id_task);

        $firstLinkedItemData = $this->getFirstlinkedItem($id_ticket);

        $itemsId = $ticket->getField("items_id");
        $this->fields["ID_equipment"] = empty($itemsId) ? '' : $itemsId;
        $this->fields["ticket"] = $id_ticket . "-" . $id_task;
        $this->fields["task_date"] = $this->convDate($task->getField("date"));

        // The complete name of the technician
        $tech = getUserName($task->getField("users_id_tech"));
        $requester = $this->getRequesterData($id_ticket);
        $addFields = $this->getAdditionalFieldsData($requester['id'], $id_ticket);

        $this->fields["descriptionMerged"] = $ticket->fields['name'] . " / " . $ticket->fields['content'];
        $this->fields["technician"] = $tech;
        $this->fields["distancia"] = $addFields["distancia"];
        $this->fields["agencia"] = $addFields["agencia"];
        $this->fields["origin"] = $addFields["origin"];
        //asset
        $this->fields["nameAsset"] = $firstLinkedItemData['nameAsset'];
        $this->fields["idAsset"] = $firstLinkedItemData['idAsset'];
        $this->fields["type"] = $firstLinkedItemData['type'];

        $this->fields["requester"] = $requester['requester'];
        $this->fields["requesterName"] = $requester['name'];
        $this->fields["requesterNumber"] = $requester['number'];
        $this->fields["requesterPhone"] = $requester['phone'];
        $this->fields["customer"] = $this->getLoginCustomer($id_ticket);
        $this->fields["num_contract"] = $firstLinkedItemData['targetItemData']['contractNum'];
        $this->fields["targetItemLocation"] = $firstLinkedItemData['targetItemData']['targetItemLocation'];
        $this->fields["serial"] = $firstLinkedItemData['targetItemData']['serial'];
        $this->fields["model"] = $firstLinkedItemData['targetItemData']['model'];
        $this->fields["description"] = $ticket->fields["content"];
        $this->fields["title_ticket"] = $ticket->fields["name"];
    }

    function getFirstlinkedItem($id_ticket)
    {
        $item_ticket = new Item_Ticket();
        $linked_items = $item_ticket->find("`tickets_id` = " . $id_ticket);
        $targetItem = '';
        $ItemtypeLinkedData = [
            'itemtypeName' => '',
            'model' => '',
            'serial' => '',
            'contractNum' => '',
            'targetItemLocation' => '',
            'type' => ''
        ];
        if ($linked_items) {
            foreach ($linked_items as $value) {
                $targetItem = $value;
                break;
            }
            $ItemtypeLinkedData = $this->getItemtypeLinkedData($targetItem['itemtype'], $targetItem['items_id']);
            $item_ticket->getFromDB($targetItem['id']);
        }
        return ['targetItem' => $targetItem,
            'targetItemData' => $ItemtypeLinkedData,
            'nameAsset' => $ItemtypeLinkedData['itemtypeName'],
            'idAsset' => $item_ticket->getField('id'),
            'type' => $ItemtypeLinkedData['type']
        ];
    }

    /**
     * origin/distancia fields here
     */
    function getAdditionalFieldsData($requesterId, $id_ticket)
    {
        global $DB;
        $userdistancias = '';
        $origin = '';
        $agencia = '';
        $tempOrigin = '';

        $query = $DB->query('SELECT dist.distanciafield, agenc.completename
        FROM glpi_plugin_fields_userdistancias as dist
        RIGHT OUTER JOIN glpi_plugin_fields_agenciarcsfielddropdowns agenc
        ON agenc.id = dist.plugin_fields_agenciarcsfielddropdowns_id
        WHERE items_id = ' . $requesterId);

        if ($query)
            $userdistancias = mysqli_fetch_row($query);

        $query = $DB->query('SELECT noramentofield FROM glpi_plugin_fields_ticketnoramentos WHERE items_id = ' . $id_ticket);
        if ($query)
            $tempOrigin = mysqli_fetch_row($query);

        $distancia = $userdistancias && isset($userdistancias[0]) ? $userdistancias[0] : '';
        $agencia = $userdistancias && isset($userdistancias[1]) ? $userdistancias[1] : '';
        $origin = $tempOrigin && isset($tempOrigin[0]) ? $tempOrigin[0] : '';


        return ['distancia' => $distancia, 'agencia' => $agencia, 'origin' => $origin];
    }

    function getRequesterData($ticket_id)
    {
        $requesterId = 0;
        $requesterName = '';
        $phone = '';
        $requesterNumber = '';
        $requester = '';
        $ticket = new Ticket();
        $ticket->getFromDB($ticket_id);
        $requesterArr = $ticket->getTicketActors();

        foreach ($requesterArr as $userId => $actor) {
            if ($actor == CommonITILActor::REQUESTER) {
                $requesterId = $userId;
                break;
            }
        }

        if ($requesterId) {
            $requesterUser = new User();
            $requesterUser->getFromDB($requesterId);

            $requesterName = self::getOnlyUserName($requesterId);
            $requester = $requesterUser->fields['name'];
            $phone = $requesterUser->fields['mobile'];
            $requesterNumber = $requesterUser->fields['registration_number'];
        }

        return ['id' => $requesterId, 'name' => $requesterName, 'phone' => $phone, 'number' => $requesterNumber, 'requester' => $requester];
    }

    /**
     * Nom du modèle (de l'élement associé au ticket)
     * @param unknown $ticket_id
     * @param string $associated_element_itemtype
     * @return string
     */

    function getOnlyUserName($id)
    {
        global $DB;
        $res = $DB->query('SELECT firstname FROM glpi_users WHERE id = ' . $id);
        if ($res) {
            $res = mysqli_fetch_row($res);
            if ($res && isset($res[0]))
                $res = $res[0];
        } else
            $res = "";
        return $res;
    }

    function getItemtypeLinkedData($associated_element_itemtype, $itemtype_id)
    {
        $itemtype = new $associated_element_itemtype();
        $itemtype->getFromDB($itemtype_id);

        return [
            'itemtypeName' => self::getItemtypeName($itemtype),
            'model' => self::getModelOfItem($itemtype),
            'serial' => $itemtype->getField('serial'),
            'contractNum' => self::getItemtypeContractNum($itemtype, $associated_element_itemtype, $itemtype_id),
            'targetItemLocation' => self::getLocationFromItemtype($itemtype),
            'type' => self::getTypeOfItem($itemtype)
        ];
    }


    static function getItemtypeContractNum($itemtype, $associated_element_itemtype, $itemtype_id)
    {
        $contract = new Contract();
        $contract_items = new Contract_Item();
        $possible_contracts = $contract_items->find("items_id = " . $itemtype_id . " && itemtype = '" . $associated_element_itemtype . "'");
        foreach ($possible_contracts as $item)
            $contract->getFromDB($item['contracts_id']);

        $tempContract = $contract->getField('num');
        return $tempContract != 'N/A' ? $tempContract : ' ';
    }

    static function getItemtypeName($itemtype)
    {
        $temp = $itemtype->getField('name');
        return $temp != 'N/A' ? $temp : '';
    }

    static function getLocationFromItemtype($itemtype)
    {
        $tempTargeLocId = $itemtype->getField('locations_id');
        $loc = new Location();
        $loc->getFromDB($tempTargeLocId);
        $tempTargeLoc = $loc->getField('name');
        return ($tempTargeLoc != 'N/A') ? $tempTargeLoc : ' ';
    }

    /**
     * @param $object   itemtype class object
     * @return array    name and value of type field
     */
    static function getTypeFieldName($object)
    {
        foreach (get_object_vars($object)['fields'] as $key => $value)
            if (strpos($key, 'types'))
                return [$key, $value];
        return ['', ''];
    }

    /**
     * @param $object   itemtype class object
     * @return array    name and value of model field
     */
    static function getModelFieldName($object)
    {
        foreach (get_object_vars($object)['fields'] as $key => $value)
            if (strpos($key, 'models'))
                return [$key, $value];
        return ['', ''];
    }

    /**
     * @param $typeAndValueOfItem array($typeName, $typeValue)
     * check if type name is native for glpi then prepend glpi_ to table name for search
     * if not then just replace _id (because plugin tables not starting with glpi_ suffix)
     */
    static function getItemtypeNameFromFieldName($typeAndValueOfItem)
    {
        if ($typeAndValueOfItem[0] && $typeAndValueOfItem[1]) {
            global $DB;
            $dbTableName = '';
            if (strpos('plugin_', $typeAndValueOfItem[0]) === false)
                $dbTableName = 'glpi_';

            $dbTableName .= str_replace('_id', '', $typeAndValueOfItem[0]);

            $nameOfType = mysqli_fetch_row($DB->query('SELECT name FROM ' . $dbTableName . ' WHERE id = ' . $typeAndValueOfItem[1]));
            $nameOfTypeValue = ($nameOfType && isset($nameOfType[0]))?$nameOfType[0]:'';
            return $nameOfTypeValue;
        } else
            return '';
    }

    /**
     * @param $itemtype     object of ticket linked itemtype
     * @return string       type name field of linked element
     */
    static function getTypeOfItem($itemtype)
    {
        $typeAndValueOfItem = self::getTypeFieldName($itemtype);
        return self::getItemtypeNameFromFieldName($typeAndValueOfItem);
    }

    /**
     * @param $itemtype     object of ticket linked itemtype
     * @return string       model name field of linked element
     */
    static function getModelOfItem($itemtype)
    {
        $modelAndValueOfItem = self::getModelFieldName($itemtype);
        return self::getItemtypeNameFromFieldName($modelAndValueOfItem);
    }


    /**
     * Identifiant du client, càd identifiant du demandeur du ticket (le 1er)
     * @param unknown $id_ticket
     * @return string
     */
    function getLoginCustomer($id_ticket)
    {
        $ticket_usr = new Ticket_User();
        $requesters = $ticket_usr->find("`tickets_id` = $id_ticket AND `type` = " . Ticket_User::REQUESTER, "", "1");

        if (empty($requesters)) {
            return NOT_AVAILABLE;
        }

        // Name of first requester :
        $requester = array_shift($requesters);
        $user = new User();
        $user->getFromDB($requester['users_id']);
        return $user->fields["name"];
    }

    function getFooterFields()
    {

        // Get root entity :
        $entity = new Entity();
        $entity->getFromDB(0);

        $this->fields["company_name"] = $entity->getField("name");

        $addr = str_replace("<br />", ", ", nl2br($entity->getField("address")));

        $this->fields["addr"] = $addr;

        $this->fields["addr:city"] = $entity->getField("town");
        $this->fields["addr:country"] = $entity->getField("country");

        // Phone of root entity (with phone on international format)
        $phone = nl2br($entity->getField("phonenumber"));

        $this->fields["phone"] = str_replace("<br />", ", ", $phone);

        // Mobile phone is saved in the field 'state' (because no have a field for mobile phone in GLPI)
        $this->fields["phone:mobile"] = $entity->getField("state");
    }

    function getFooter()
    {
        $out = $this->fields["company_name"] . " - " . $this->fields["addr"] . " - ";
        $out .= $this->fields["addr:city"] . " - " . $this->fields["addr:country"];
        $out .= " " . __("Ph.", 'ficheintervention') . " " . $this->fields["phone"];
        $out .= " " . __("GSM:", 'ficheintervention') . " " . $this->fields["phone:mobile"];
        return $out;
    }

}