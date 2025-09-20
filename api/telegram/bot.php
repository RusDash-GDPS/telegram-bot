<?php
$data = json_decode(file_get_contents("php://input"), true);
$botUserName = "@bot"; # Тут ваш юз бота
$supportGroupID = "0"; # Тут ваша группа саппорта
$botUserID = "0"; # Ваш ID бота
function sendTelegramQuery($method, $send_data)
{
    $keys = include __DIR__ . "путь/до/вашего_токена";
    $bot_token = $keys["telegram_key"];
    /* На случай если нет файла с токенами замените верхние две строки на
    $bot_token = "токен_бота"; */
    $ch = curl_init("https://api.telegram.org/bot{$bot_token}/{$method}");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $send_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
}

if(!empty($data))
{
    # Обработка запросов нажатия на кнопки
    if(isset($data["callback_query"]))
    {
        $callbackQuery = $data["callback_query"];
        $callbackQueryID = $callbackQuery["id"];
        $callbackData = $callbackQuery["data"];
        $method = 'answerCallbackQuery';
        
        $callbackDataArray = explode(":", $callbackData);
        switch($callbackDataArray[0])
        {
            case "lv_del_btn":
                require_once "../../incl/lib/connection.php";
                $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $accQuery->execute([":uID" => $callbackDataArray[1]]);
                if($accQuery->rowCount() == 0)
                {
                    $data = [
                    'callback_query_id' => $callbackQueryID,
                    'text' => 'Иди нахуй',
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
                sendTelegramQuery($mtd, $dt);
                $data = [
                    'callback_query_id' => $callbackQueryID,
                    'text' => 'Уровень был удалён. Сообщение с информацией про уровень так же было удалено.',
                    'show_alert' => true,
                    'cache_time' => 0
                ];
            break;
            case "lv_change_desc_btn":
                require_once "../../incl/lib/connection.php";
                $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $accQuery->execute([":uID" => $callbackDataArray[1]]);
                if($accQuery->rowCount() == 0)
                {
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
                sendTelegramQuery($mtd, $dt);
            break;
            case "lv_name_change_btn":
                require_once "../../incl/lib/connection.php";
                $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $accQuery->execute([":uID" => $callbackDataArray[1]]);
                if($accQuery->rowCount() == 0)
                {
                    $data = [
                    'callback_query_id' => $callbackQueryID,
                    'text' => 'Иди нахуй',
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
                sendTelegramQuery($mtd, $dt);
            break;
        }
        sendTelegramQuery($method, $data);
    }
    $message = explode(" ", $data["message"]["text"]);
    $command = $message[0];
    
    switch($command)
    {
        default:
            require_once "../../incl/lib/connection.php";
            $stateQuery = $db->prepare("SELECT stateData FROM tg_users WHERE userID LIKE :uID");
            $stateQuery->execute([":uID" => $data["message"]["from"]["id"]]);
            $stateDataArray = $stateQuery->fetchColumn();
            
            $stateData = explode(":", $stateDataArray);
            if(isset($data["message"]["reply_to_message"]["from"]["id"]) && str_starts_with($data["message"]["chat"]["id"], "-"))
            {
              if($data["message"]["reply_to_message"]["from"]["id"] == $botUserID && $data["message"]["reply_to_message"]["forward_from"]["id"])
              {
                $method = "sendMessage";
                $send_data = [
                  'chat_id' => $data["message"]["reply_to_message"]["forward_from"]["id"],
                  'text' => $data["message"]["text"]
                ];
              }
            }
            if($stateData[0] == 1)
            {
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
            if($stateData[0] == 2)
            {
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
            if($stateData[0] == 3)
            {
              if($stateData[1] == 0)
              {
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
                'chat_id' => $supportGroupID,
                "text" => "*ℹ️ Открыт новый тикет!*\nНапишите /tclose {$data["message"]["from"]["id"]}, чтобы закрыть тикет.\nТема тикета: *{$theme}*\nОтвечайте на пересылаемые сообщения, чтобы писать в тикет.",
                'parse_mode' => 'Markdown'
            ];
                sendTelegramQuery($mtd, $dt);
              }
                $ticketQuery = $db->prepare("SELECT stateData FROM tg_users WHERE userID LIKE :uID");
                $ticketQuery->execute([":uID" => $data["message"]["from"]["id"]]);
                $stateData = $ticketQuery->fetchColumn();

                $theme = explode(":", $stateData)[1];
              if(!str_starts_with($data["message"]["chat"]["id"], "-") && $data["message"]["text"] != $theme)
              {
                              $method = "forwardMessage";
              $send_data = [
                'chat_id' => $supportGroupID,
                'from_chat_id' => $data["message"]["chat"]["id"],
                'message_id' => $data["message"]["message_id"]
              ];
              }
            }
        break;
        case "/tclose":
        case "/tclose{$botUserName}":
             require_once "../../incl/lib/connection.php";
             if(str_starts_with($data["message"]["chat"]["id"], "-") && $data["message"]["chat"]["id"] == $supportGroupID)
             {
               if(empty($message[1]))
               {
                   $method = "sendMessage";
                   $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Пожалуйста, укажите ID чата вторым аргументом\n(P.S: Он есть в сообщении об открытии этого тикета)*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
                 break;
               }
               $ticketQuery = $db->prepare("SELECT stateData FROM tg_users WHERE userID LIKE :uID");
               $ticketQuery->execute([":uID" => $message[1]]);
               if($ticketQuery->rowCount() == 0)
               {
                  $method = "sendMessage";
                   $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Тикет в указаном чате не найден*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
                 break;
               }
               $ticketQuery = $db->prepare("UPDATE tg_users SET stateData = '0' WHERE userID LIKE :uID");
               $ticketQuery->execute([":uID" => $message[1]]);
               $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "ℹ️ *Тикет закрыт*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
               $mtd = "sendMessage";
               $dt = [
                "chat_id" => $message[1],
                "text" => "ℹ️ *Тикет закрыт админом*",
                "parse_mode" => "Markdown"
            ];
               sendTelegramQuery($mtd, $dt);
             } else {
               $ticketQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
               $ticketQuery->execute([":uID" => $data["message"]["from"]["id"]]);
               if($ticketQuery->rowCount() == 0)
               {
                $method = "sendMessage";
                $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Вы не привязали аккаунт*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
                 break;
               }
               $stateDt = $ticketQuery->fetch();
               if($stateDt == 0)
               {
                 $method = "sendMessage";
                $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Вы не открывали тикет*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
                 break;
               } else {
                 $stateData = explode(":", $stateDt["stateData"]);
                 if($stateData[0] != "3")
                 {
                                    $method = "sendMessage";
                $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Вы не открывали тикет*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
                   break;
                 }
                 $ticketQuery = $db->prepare("UPDATE tg_users SET stateData = '0' WHERE userID LIKE :uID");
                 $ticketQuery->execute([":uID" => $data["message"]["from"]["id"]]);
                 $method = "sendMessage";
                 $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "ℹ️ *Тикет закрыт*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
                 $mtd = "sendMessage";
                 $dt = [
                "chat_id" => $supportGroupID,
                "text" => "ℹ️ *Тикет ".$data["message"]["chat"]["id"]." закрыт автором*",
                "parse_mode" => "Markdown"
            ];
                 sendTelegramQuery($mtd, $dt);
               }
             }
        break;
        case "/start":
        case "/start{$botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*👋 Добро пожаловать*\n\n😊 Вас приветствует сервисный бот нашего уютного GDPS-проекта. Подключите GDPS-аккаунт и совершайте действия на GDPS даже не заходя в него.\n\n✏️ Так же вы можете открыть тикет поддержки, вам ответят администраторы проекта.\n\n_❗Указывайте данные от GDPS-аккаунта только в личке бота, с использованием споилера ('||' по бокам пароля).\n\n😕 Все действия с ботом напрямую связаны с GDPS! Взаимодействие с ботом может повлиять на ваши игровые данные_.",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/tclose":
        case "/tclose{$botUserName}":
        break;
        case "/lv":
        case "/lv{$botUserName}":
            require_once "../../incl/lib/connection.php";
            require_once "../../incl/lib/mainLib.php";
            if(empty($message[1]))
            {
                $method = "sendMessage";
                $send_data = [
                'chat_id' => $data["message"]["chat"]["id"],
                'text' => '<b>❌ Укажите ID уровня вторым аргументом</b>',
                'parse_mode' => 'html',
                'reply_to_message_id' => $data["message"]["message_id"]
            ];
            break;
            }
            $lvQuery = $db->prepare("SELECT * FROM levels WHERE levelID = :id");
            $lvQuery->execute([":id" => $message[1]]);
            if($lvQuery->rowCount() == 0)
            {
                $method = "sendMessage";
                $send_data = [
                'chat_id' => $data["message"]["chat"]["id"],
                'text' => '<b>❌ Уровень не найден</b>',
                'parse_mode' => 'html',
                'reply_to_message_id' => $data["message"]["message_id"]
            ];
            break;
            }
            $lvData = $lvQuery->fetch();
            $statLabel = $lvData["likes"] >= 0 ? "👍 ".$lvData["likes"] : "👎 ".-($lvData["likes"]);
            $statLabel .= " ⬇️ {$lvData['downloads']}";
            $descLabel = !empty(base64_decode($lvData['levelDesc'])) ? base64_decode($lvData['levelDesc']) : "Описание отсутствует";
            
            $gs = new mainLib();
            $levelDiff = $gs->getDifficulty($lvData['starDifficulty'], $lvData['starAuto'], $lvData['starDemon']);
        $levelDiff .= $lvData['starStars'] > 0 ? ", ".$lvData['starStars']." ⭐" : "";
            $lvTypes = ['', '(Фьючер)', '(Эпик)', '(Легендарный)', '(Мифический)'];
        $songInfo = $lvData["songID"] > 0 ? $gs->getSongInfo($lvData['songID'])['name']."\nBy: ".$gs->getSongInfo($lvData['songID'])['authorName'] : $gs->getAudioTrack($lvData['audioTrack']);
            
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*🔍 {$lvData['levelName']}*\n{$lvData['userName']}\n_{$descLabel}_\n{$levelDiff} {$lvTypes[$lvData['starEpic'] + ($lvData['starFeatured'] ? 1 : 0)]}\n{$statLabel}\n```🎵Song\n{$songInfo}```",
                "parse_mode" => "Markdown"
            ];
        break;
        case "/link":
        case "/link{$botUserName}":
            require_once "../../incl/lib/connection.php";
            require_once "../../incl/lib/generatePass.php";
            require_once "../../incl/lib/mainLib.php";
            $ps = new GeneratePass();
            $gs = new mainLib();
            if(str_starts_with($data["message"]["chat"]["id"], "-"))
            {
                          $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*❌ Подключить аккаунт можно только в личке с ботом*",
                "parse_mode" => "Markdown"
            ];
              $mtd = "deleteMessage";
              $dt = [
                "chat_id" => $data["message"]["chat"]["id"],
                "message_id" => $data["message"]["message_id"]
              ];
              sendTelegramQuery($mtd, $dt);
            break;
            }
            if(empty($message[1]) || empty($message[2]))
            {
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*❌ Неверный синтаксис*\n```✏️Синтаксис\n/link <ник> <пароль>```\n_❗Указывайте данные от аккаунта в приватке, по бокам пароля поставьте '||', пример: ||пароль||_.",
                "parse_mode" => "Markdown"
            ];
            break;
            }
            $valid = $ps->isValidUsrname($message[1], $message[2]);
            if(!$valid)
            {
                $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*❌ Неверные данные*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;
            }
            $accRow = $db->prepare("SELECT * FROM tg_users WHERE accID = :accID");
            $accRow->execute([":accID" => $gs->getAccountIDFromName($message[1])]);
            if($accRow->rowCount() > 0)
            {
                $accData = $accRow->fetch();
                if($accData["userID"] == $data["message"]["from"]["id"])
                {
                    $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*ℹ️ Вы уже привязали аккаунт.*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;
                } else {
                $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*ℹ️ Этот аккаунт уже подключён к телеграм аккаунту.*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;
                }
            }
            $accID = $gs->getAccountIDFromName($message[1]);
            $loginQuery = $db->prepare("INSERT INTO tg_users (accID, userID) VALUES (:accID, :userID)");
            $loginQuery->execute([":accID" => $accID, "userID" => $data["message"]["from"]["id"]]);
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*✅ Успешно!\n🔍 Вы авторизовались как:* _{$message[1]}_.\nℹ️ Вы можете управлять своими уровнями и действиями на аккаунте.",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/unlink":
        case "/unlink{$botUserName}":
             require_once "../../incl/lib/connection.php";
             $accQuery = $db->prepare("SELECT accID FROM tg_users WHERE userID LIKE :uID");
             $accQuery->execute([":uID" => $data["message"]["from"]["id"]]);
             if($accQuery->rowCount() == 0)
             {
               $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*❌ Вы не привязывали аккаунт*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
               break;
             }
             # Наш слоняра ZOV ZOV ZOV ZZZZ
             $deleteQuery = $db->prepare("DELETE FROM tg_users WHERE userID LIKE :uID LIMIT 1");
             $deleteQuery->execute([":uID" => $data["message"]["from"]["id"]]);
             $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*ℹ️ Вы отвязали аккаунт!*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/lvedit":
        case "/lvedit{$botUserName}":
            require_once "../../incl/lib/connection.php";
            require_once "../../incl/lib/mainLib.php";
            $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
            $accQuery->execute([":uID" => $data["message"]["from"]["id"]]);
            if($accQuery->rowCount() == 0)
            {
            $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*❌ Вы не привязали аккаунт!*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;
            }
            if(empty($message[1]))
            {
                $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*✏️ Укажите ID вашего уровня!*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;
            }
            $accData = $accQuery->fetch();
            $gs = new mainLib();
            $lvQuery = $db->prepare("SELECT * FROM levels WHERE levelID = :lID AND userName LIKE :uName");
            $lvQuery->execute([":lID" => $message[1], ":uName" => $gs->getAccountName($accData["accID"])]);
            if($lvQuery->rowCount() == 0)
            {
                $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*❌ Уровень с таким ID и при этом в вашем владении не найден.*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;
            }
            $lvData = $lvQuery->fetch();
            $lvDesc = !empty(base64_decode($lvData["levelDesc"])) ? base64_decode($lvData["levelDesc"]) : "Описание отсутствует";
            $buttons = [
                [
                    ['text' => '🧨 Удалить', "callback_data" => "lv_del_btn:{$accData['userID']}:{$lvData['levelID']}"],
                    ['text' => '✏️ Описание', "callback_data" => "lv_change_desc_btn:{$accData['userID']}:{$lvData['levelID']}"]
                ],
                [
                    ['text' => '🖊️ Переименовать', "callback_data" => "lv_name_change_btn:{$accData['userID']}:{$lvData['levelID']}"]
                ]
            ];
            $markup = json_encode([
                'inline_keyboard' => $buttons,
            ]);
            $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*✏️ Уровень {$lvData['levelName']}*\n_{$lvDesc}_",
                "reply_markup" => $markup,
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/daily":
        case "/daily{$botUserName}":
             require_once "../../incl/lib/connection.php";
             require_once "../../incl/lib/mainLib.php";
             $gs = new mainLib();
             $dailyQuery = $db->prepare("SELECT levelID FROM dailyfeatures WHERE type = 0 ORDER BY dailyfeatures.feaID DESC LIMIT 1");
             $dailyQuery->execute();
             $dailyID = $dailyQuery->fetchColumn();
             # LevelInfo для сообщения
             $authorQuery = $db->prepare("SELECT userName FROM levels WHERE levelID = :lID");
             $authorQuery->execute([":lID" => $dailyID]);
             $author = $authorQuery->fetchColumn();
             $songQuery = $db->prepare("SELECT songID FROM levels WHERE levelID = :lID");
             $songQuery->execute([":lID" => $dailyID]);
             $song = $songQuery->fetchColumn();
             $levelStats = $gs->getLevelStats($dailyID);
             $likesLabel = $levelStats["likes"] >= 0 ? $levelStats["likes"]." 👍" : -($levelStats["likes"])." 👎";
             $songInfo = $song > 0 ? $gs->getSongInfo($song)['name']."\nBy: ".$gs->getSongInfo($song)['authorName'] : $gs->getAudioTrack($song);
             # Рейт тип
             $rateQuery = $db->prepare("SELECT starEpic, starFeatured, starStars, starDemon FROM levels WHERE levelID = :lID");
             $rateQuery->execute([":lID" => $dailyID]);
             $rate = $rateQuery->fetch();
             $rateTypes = ['', 'Фьючер', 'Эпик', 'Лега', 'Мифик'];
             $rateType = $rate["starEpic"] + ($rate["starFeatured"] ? 1 : 0);
             $rateString = empty($rateTypes[$rateType]) ? '' : ", (".$rateTypes[$rateType].")";
             $stars = $rate["starStars"] > 0 ? ", ".$rate["starStars"]." ⭐" : '';
             $diff = $rate["starDemon"] > 0 ? $gs->getDemonDiff($dailyID)." Demon" : $gs->getLevelDiff($dailyID);
             $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*👑 ".$gs->getLevelName($dailyID)."*\nBy: {$author}\n".$diff."{$stars} {$rateString}\n_".$gs->getDesc($dailyID)."_\n{$likesLabel} {$levelStats["dl"]} ⬇️\n```🎵Сонг\n{$songInfo}```",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/weekly":
        case "/weekly{$botUserName}":
             require_once "../../incl/lib/connection.php";
             require_once "../../incl/lib/mainLib.php";
             $gs = new mainLib();
             $dailyQuery = $db->prepare("SELECT levelID FROM dailyfeatures WHERE type = 1 ORDER BY dailyfeatures.feaID DESC LIMIT 1");
             $dailyQuery->execute();
             $dailyID = $dailyQuery->fetchColumn();
             # LevelInfo для сообщения
             $authorQuery = $db->prepare("SELECT userName FROM levels WHERE levelID = :lID");
             $authorQuery->execute([":lID" => $dailyID]);
             $author = $authorQuery->fetchColumn();
             $songQuery = $db->prepare("SELECT songID FROM levels WHERE levelID = :lID");
             $songQuery->execute([":lID" => $dailyID]);
             $song = $songQuery->fetchColumn();
             $levelStats = $gs->getLevelStats($dailyID);
             $likesLabel = $levelStats["likes"] >= 0 ? $levelStats["likes"]." 👍" : -($levelStats["likes"])." 👎";
             $songInfo = $song > 0 ? $gs->getSongInfo($song)['name']."\nBy: ".$gs->getSongInfo($song)['authorName'] : $gs->getAudioTrack($song);
             # Рейт тип
             $rateQuery = $db->prepare("SELECT starEpic, starFeatured, starStars, starDemon FROM levels WHERE levelID = :lID");
             $rateQuery->execute([":lID" => $dailyID]);
             $rate = $rateQuery->fetch();
             $rateTypes = ['', 'Фьючер', 'Эпик', 'Лега', 'Мифик'];
             $rateType = $rate["starEpic"] + ($rate["starFeatured"] ? 1 : 0);
             $rateString = empty($rateTypes[$rateType]) ? '' : ", (".$rateTypes[$rateType].")";
             $stars = $rate["starStars"] > 0 ? ", ".$rate["starStars"]." ⭐" : '';
             $diff = $rate["starDemon"] > 0 ? $gs->getDemonDiff($dailyID)." Demon" : $gs->getLevelDiff($dailyID);
             $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*👑 ".$gs->getLevelName($dailyID)."*\nBy: {$author}\n".$diff."{$stars} {$rateString}\n_".$gs->getDesc($dailyID)."_\n{$likesLabel} {$levelStats["dl"]} ⬇️\n```🎵Сонг\n{$songInfo}```",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/event":
        case "/event{$botUserName}":
             require_once "../../incl/lib/connection.php";
             require_once "../../incl/lib/mainLib.php";
             $gs = new mainLib();
             $dailyQuery = $db->prepare("SELECT levelID FROM dailyfeatures WHERE type = 2 ORDER BY dailyfeatures.feaID DESC LIMIT 1");
             $dailyQuery->execute();
             $dailyID = $dailyQuery->fetchColumn();
             # LevelInfo для сообщения
             $authorQuery = $db->prepare("SELECT userName FROM levels WHERE levelID = :lID");
             $authorQuery->execute([":lID" => $dailyID]);
             $author = $authorQuery->fetchColumn();
             $songQuery = $db->prepare("SELECT songID FROM levels WHERE levelID = :lID");
             $songQuery->execute([":lID" => $dailyID]);
             $song = $songQuery->fetchColumn();
             $levelStats = $gs->getLevelStats($dailyID);
             $likesLabel = $levelStats["likes"] >= 0 ? $levelStats["likes"]." 👍" : -($levelStats["likes"])." 👎";
             $songInfo = $song > 0 ? $gs->getSongInfo($song)['name']."\nBy: ".$gs->getSongInfo($song)['authorName'] : $gs->getAudioTrack($song);
             # Рейт тип
             $rateQuery = $db->prepare("SELECT starEpic, starFeatured, starStars, starDemon FROM levels WHERE levelID = :lID");
             $rateQuery->execute([":lID" => $dailyID]);
             $rate = $rateQuery->fetch();
             $rateTypes = ['', 'Фьючер', 'Эпик', 'Лега', 'Мифик'];
             $rateType = $rate["starEpic"] + ($rate["starFeatured"] ? 1 : 0);
             $rateString = empty($rateTypes[$rateType]) ? '' : ", (".$rateTypes[$rateType].")";
             $stars = $rate["starStars"] > 0 ? ", ".$rate["starStars"]." ⭐" : '';
             $diff = $rate["starDemon"] > 0 ? $gs->getDemonDiff($dailyID)." Demon" : $gs->getLevelDiff($dailyID);
             $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*👑 ".$gs->getLevelName($dailyID)."*\nBy: {$author}\n".$diff."{$stars} {$rateString}\n_".$gs->getDesc($dailyID)."_\n{$likesLabel} {$levelStats["dl"]} ⬇️\n```🎵Сонг\n{$songInfo}```",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
        case "/ticket":
        case "/ticket{$botUserName}":
             require_once "../../incl/lib/connection.php";
             if(str_starts_with($data["message"]["chat"]["id"], "-"))
             {
               $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Тикет можно открыть только в личке*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
               break;
             }
             $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
             $accQuery->execute([":uID" => $data["message"]["from"]["id"]]);
             if($accQuery->rowCount() == 0)
             {
               $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Боту нужно хранить информацию о вашем тикете. Привяжите аккаунт!*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
               break;
             }
             $accData = $accQuery->fetch();
             $stateData = explode(":", $accData["stateData"]);
             if($stateData[0] == 3)
             {
               $method = "sendMessage";
               $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "❌ *Вы уже открыли тикет, пропишите /tclose, чтобы закрыть.*",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
               break;
             }
             $stateQuery = $db->prepare("UPDATE tg_users SET stateData = '3:0' WHERE userID LIKE :uID");
             $stateQuery->execute(["uID" => $data["message"]["from"]["id"]]);
             $method = "sendMessage";
             $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "✅ *Тикет открыт, введите тему тикета:*\n❗Откройте пересылку сообщений всем в настройках конфиденциальности пока не закроете тикет, иначе тикет не будет работать.",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
        break;
    }
    
    sendTelegramQuery($method, $send_data);
}

  






