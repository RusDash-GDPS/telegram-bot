<?php
include "../../incl/lib/mainLib.php";

class BotLib
{

    public $supportGroupID = "0000";
    private $bot_token = "ТОКЕН_БОТА";
    public $botUserName = "@bot"; # Это для команд по типу /start@bot
    public $botUserID = "0000";

    function __construct()
    {

    }

    public function sendTelegramQuery($method, $send_data)
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->bot_token}/{$method}");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    public function getStateData($userID)
    {
        include "../../incl/lib/connection.php";

        $stateQuery = $db->prepare("SELECT stateData FROM tg_users WHERE userID LIKE :uID");
        $stateQuery->execute([":uID" => $userID]);

        if ($stateQuery->rowCount() == 0) {
            return false;
        }

        $stateData = $stateQuery->fetchColumn();

        $stateDataArray = explode(":", $stateData);

        return $stateDataArray;
    }

    public function forwardMessage($message)
    {

        if (!$message) {
            return false;
        }

        $data = [
            "chat_id" => $this->supportGroupID,
            "from_chat_id" => $message["chat"]["id"],
            "message_id" => $message["message_id"]
        ];

        $this->sendTelegramQuery("forwardMessage", $data);
    }

    public function getLevelMessage($levelID)
    {
        include "../../incl/lib/connection.php";
        require_once "../../incl/lib/mainLib.php";

        $gs = new mainLib();

        if (!$levelID) {
            return "*❌ Пожалуйста, укажите ID уровня*";
        }

        $levelQuery = $db->prepare("SELECT * FROM levels WHERE levelID = :lID");
        $levelQuery->execute([":lID" => $levelID]);

        if ($levelQuery->rowCount() == 0) {
            return "*❌ Уровень не найден*";
        }

        $levelData = $levelQuery->fetch();
        $statLabel = $levelData["likes"] >= 0 ? $levelData["likes"] . " 👍" : -($levelData["likes"]) . " 👎";
        $statLabel .= " " . $levelData["downloads"] . " ⬇️";

        $descLabel = !empty($levelData["levelDesc"]) ? base64_decode($levelData["levelDesc"]) : "Описание отсутствует";

        $levelDiff = $gs->getDifficulty($levelData["starDifficulty"], $levelData["starAuto"], $levelData["starDemon"]);

        $rateTypes = ['', '(Фьючер)', '(Эпик)', '(Легендарный)', '(Мифический)'];

        $songInfo = $levelData["songID"] > 0 ? $gs->getSongInfo($levelData['songID'])['name'] . "\nBy: " . $gs->getSongInfo($levelData['songID'])['authorName'] : $gs->getAudioTrack($levelData['audioTrack']);

        $levelMessageText = "*{$levelData["levelName"]}*\n{$levelData["userName"]}\n_{$descLabel}_\n{$levelDiff} {$rateTypes[$levelData["starEpic"] + ($levelData["starFeatured"] ? 1 : 0)]}\n{$statLabel}\n```🎵Сонг\n{$songInfo}```";

        return $levelMessageText;
    }

    public function sendParralelQuery($method, $data)
    {

        if (empty($method) || empty($data)) {
            return false;
        }

        $this->sendTelegramQuery($method, $data);
    }

    public function linkAccount($message)
    {
        include "../../incl/lib/connection.php";
        require_once "../../incl/lib/mainLib.php";
        include "../../incl/lib/generatePass.php";

        $gp = new GeneratePass();
        $gs = new mainLib();

        if (!$message) {
            return false;
        }

        if (str_starts_with($message["chat"]["id"], "-")) {
            $method = "deleteMessage";
            $data = [
                "chat_id" => $message["chat"]["id"],
                "message_id" => $message["message_id"]
            ];

            $this->sendParralelQuery($method, $data);

            return "*❌ Подключить аккаунт можно только в личке с ботом*";
        }

        $userQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
        $userQuery->execute([":uID" => $message["from"]["id"]]);

        if ($userQuery->rowCount() > 0) {
            return "*❌ У вас уже привязан аккаунт*";
        }

        $text = explode(" ", $message["text"]);

        if (empty($text[1]) || empty($text[2])) {
            return "*❌ Необходимо указать ник, а затем пароль*";
        }

        $valid = $gp->isValidUsrname($text[1], $text[2]);

        if (!$valid) {
            return "*❌ Привязка провалена!*";
        }

        $accID = $gs->getAccountIDFromName($text[1]);

        $loginQuery = $db->prepare("INSERT INTO tg_users (accID, userID) VALUES (:accID, :userID)");
        $loginQuery->execute([":accID" => $accID, ":userID" => $message["from"]["id"]]);

        return "*✅ Авторизация завершена!*\n_С возвращением, {$text[1]}_";
    }

    public function getPromotedLevel($type)
    {
        include "../../incl/lib/connection.php";

        if ($type == "") {
            return false;
        }

        $pQuery = $db->prepare("SELECT levelID FROM dailyfeatures WHERE type = :type ORDER BY feaID DESC LIMIT 1");
        $pQuery->execute([":type" => $type]);

        $levelID = $pQuery->fetchColumn();

        if ($type == "0") {
            $levelMessage = "👑 ";
        }

        if ($type == "1") {
            $levelMessage = "👿 ";
        }

        if ($type == "2") {
            $levelMessage = "✨ ";
        }

        $levelMessage .= $this->getLevelMessage($levelID);

        return $levelMessage;
    }

    public function answerCallbackQuery($callbackDataArray, $callbackQuery)
    {
        switch ($callbackDataArray[0]) {
            case "lv_del_btn":
                require_once "../../incl/lib/connection.php";

                $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $accQuery->execute([":uID" => $callbackDataArray[1]]);

                if ($accQuery->rowCount() == 0) {
                    $data = [
                        'callback_query_id' => $callbackQueryID,
                        'text' => 'Вы не можете выполнить это действие',
                        'show_alert' => false,
                        'cache_time' => 0
                    ];
                    break;
                }

                $delLvQuery = $db->prepare("DELETE FROM levels WHERE levelID = :lvID LIMIT 1");
                $delLvQuery->execute([":lvID" => $callbackDataArray[2]]);

                $mtd = 'deleteMessage';
                $dt = [
                    'chat_id' => $callbackQuery["message"]["chat"]["id"],
                    'message_id' => $callbackQuery["message"]["message_id"]
                ];

                $this->sendParralelQuery($mtd, $dt);

                $data = [
                    'callback_query_id' => $callbackQueryID,
                    'text' => 'Уровень успешно удалён.',
                    'show_alert' => true,
                    'cache_time' => 0
                ];
                break;
            case "lv_change_desc_btn":
                require_once "../../incl/lib/connection.php";

                $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $accQuery->execute([":uID" => $callbackDataArray[1]]);

                if ($accQuery->rowCount() == 0) {
                    $data = [
                        'callback_query_id' => $callbackQueryID,
                        'text' => 'Иди нахуй',
                        'show_alert' => false,
                        'cache_time' => 0
                    ];
                    break;
                }

                $stateQuery = $db->prepare("UPDATE tg_users SET stateData = '1:{$callbackDataArray[2]}' WHERE userID LIKE :uID");
                $stateQuery->execute([":uID" => $callbackDataArray[1]]);

                $mtd = 'editMessageText';
                $dt = [
                    'chat_id' => $callbackQuery["message"]["chat"]["id"],
                    'message_id' => $callbackQuery["message"]["message_id"],
                    "text" => "Введите новое описание уровня:\n❗Описание пишите строго на английском, иначе оно не отобразится на GDPS."
                ];

                $this->sendParralelQuery($mtd, $dt);
                break;
            case "lv_name_change_btn":
                require_once "../../incl/lib/connection.php";

                $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $accQuery->execute([":uID" => $callbackDataArray[1]]);

                if ($accQuery->rowCount() == 0) {
                    $data = [
                        'callback_query_id' => $callbackQueryID,
                        'text' => 'Вы не можете выполнить это действие',
                        'show_alert' => false,
                        'cache_time' => 0
                    ];
                    break;
                }

                $stateQuery = $db->prepare("UPDATE tg_users SET stateData = '2:{$callbackDataArray[2]}' WHERE userID LIKE :uID");
                $stateQuery->execute([":uID" => $callbackDataArray[1]]);

                $mtd = 'editMessageText';
                $dt = [
                    'chat_id' => $callbackQuery["message"]["chat"]["id"],
                    'message_id' => $callbackQuery["message"]["message_id"],
                    "text" => "Введите новое название уровня:\n❗Название пишите строго на английском, иначе оно не отобразится на GDPS."
                ];

                $this->sendParralelQuery($mtd, $dt);
                break;
        }

        $this->sendTelegramQuery("answerCallbackQuery", $data);
    }
    public function acceptByDefault($data)
    {
        require_once "../../incl/lib/connection.php";

        $stateDataArray = $this->getStateData($data["message"]["from"]["id"]);
        $stateData = $stateDataArray;

        if (isset($data["message"]["reply_to_message"]["from"]["id"]) && str_starts_with($data["message"]["chat"]["id"], "-")) {

            if ($data["message"]["reply_to_message"]["from"]["id"] == $this->botUserID && $data["message"]["reply_to_message"]["forward_from"]["id"]) {
                $method = "sendMessage";
                $send_data = [
                    'chat_id' => $data["message"]["reply_to_message"]["forward_from"]["id"],
                    'text' => $data["message"]["text"]
                ];
            }
        }

        if ($stateData[0] == 1) {
            $editLVDescQuery = $db->prepare("UPDATE levels SET levelDesc = :lvName WHERE levelID = :lID");
            $editLVDescQuery->execute([":lvName" => base64_encode($data["message"]["text"]), ":lID" => $stateData[1]]);

            $stateQuery = $db->prepare("UPDATE tg_users SET stateData = 0 WHERE userID LIKE :uID");
            $stateQuery->execute([":uID" => $data["message"]["from"]["id"]]);

            $method = 'sendMessage';
            $send_data = [
                'chat_id' => $data["message"]["chat"]["id"],
                'text' => '*ℹ️ Описание уровня успешно изменено!*',
                'parse_mode' => 'Markdown',
                'reply_to_message_id' => $data["message"]["message_id"]
            ];
        }

        if ($stateData[0] == 2) {
            $editLVNameQuery = $db->prepare("UPDATE levels SET levelName = :lvName WHERE levelID = :lID");
            $editLVNameQuery->execute([":lvName" => $data["message"]["text"], ":lID" => $stateData[1]]);

            $stateQuery = $db->prepare("UPDATE tg_users SET stateData = 0 WHERE userID LIKE :uID");
            $stateQuery->execute([":uID" => $data["message"]["from"]["id"]]);

            $method = 'sendMessage';
            $send_data = [
                'chat_id' => $data["message"]["chat"]["id"],
                'text' => '*ℹ️ Название уровня успешно изменено!*',
                'parse_mode' => 'Markdown',
                'reply_to_message_id' => $data["message"]["message_id"]
            ];
        }

        if ($stateData[0] == 3) {

            if ($stateData[1] == 0) {
                $theme = $data["message"]["text"];

                $stateQuery = $db->prepare("UPDATE tg_users SET stateData = '3:{$theme}' WHERE userID LIKE :uID");
                $stateQuery->execute([":uID" => $data["message"]["from"]["id"]]);

                $method = 'sendMessage';
                $send_data = [
                    'chat_id' => $data["message"]["chat"]["id"],
                    "text" => "*ℹ️ Тикет открыт. Все сообщения пересылаются админам*\nНапишите /tclose, чтобы закрыть тикет.",
                    'parse_mode' => 'Markdown',
                    'reply_to_message_id' => $data["message"]["message_id"]
                ];

                $mtd = 'sendMessage';
                $dt = [
                    'chat_id' => $this->supportGroupID,
                    "text" => "*ℹ️ Открыт новый тикет!*\nНапишите /tclose {$data["message"]["from"]["id"]}, чтобы закрыть тикет.\nТема тикета: *{$theme}*\nОтвечайте на пересылаемые сообщения, чтобы писать в тикет.",
                    'parse_mode' => 'Markdown'
                ];
                $this->sendParralelQuery($mtd, $dt);
            }

            $ticketQuery = $db->prepare("SELECT stateData FROM tg_users WHERE userID LIKE :uID");
            $ticketQuery->execute([":uID" => $data["message"]["from"]["id"]]);

            $stateData = $ticketQuery->fetchColumn();

            $theme = explode(":", $stateData)[1];

            if (!str_starts_with($data["message"]["chat"]["id"], "-") && $data["message"]["text"] != $theme) {
                $this->forwardMessage($data["message"]);
            }
        }

        $this->sendTelegramQuery($method, $send_data);

    }
}