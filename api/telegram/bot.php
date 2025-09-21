<?php
include "botLib.php";

$bl = new BotLib();

$data = json_decode(file_get_contents("php://input"), true);

if (!empty($data)) {

    if (isset($data["callback_query"])) {
        $callbackQuery = $data["callback_query"];
        $callbackQueryID = $callbackQuery["id"];
        $callbackData = $callbackQuery["data"];
        $method = 'answerCallbackQuery';

        $callbackDataArray = explode(":", $callbackData);

        $bl->answerCallbackQuery($callbackDataArray, $callbackQuery);
    }

    $message = explode(" ", $data["message"]["text"]);
    $command = $message[0];

    switch ($command) {
        default:
            $bl->acceptByDefault($data);
            break;

        case "/tclose":
        case "/tclose{$bl->botUserName}":
            require_once "../../incl/lib/connection.php";

            if (str_starts_with($data["message"]["chat"]["id"], "-") && $data["message"]["chat"]["id"] == $bl->supportGroupID) {

                if (empty($message[1])) {
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

                if (!str_starts_with($ticketQuery->fetchColumn(), "3")) {
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

                $bl->sendParralelQuery($mtd, $dt);
            } else {
                $ticketQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
                $ticketQuery->execute([":uID" => $data["message"]["from"]["id"]]);

                if ($ticketQuery->rowCount() == 0) {
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

                if ($stateDt == 0) {
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

                    if ($stateData[0] != "3") {
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
                        "chat_id" => $bl->supportGroupID,
                        "text" => "ℹ️ *Тикет " . $data["message"]["chat"]["id"] . " закрыт автором*",
                        "parse_mode" => "Markdown"
                    ];

                    $bl->sendParralelQuery($mtd, $dt);
                }
            }
            break;

        case "/start":
        case "/start{$bl->botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => "*👋 Добро пожаловать*\n\n😊 Вас приветствует сервисный бот нашего уютного GDPS-проекта. Подключите GDPS-аккаунт и совершайте действия на GDPS даже не заходя в него.\n\n✏️ Так же вы можете открыть тикет поддержки, вам ответят администраторы проекта.\n\n_❗Указывайте данные от GDPS-аккаунта только в личке бота, с использованием споилера ('||' по бокам пароля).\n\n😕 Все действия с ботом напрямую связаны с GDPS! Взаимодействие с ботом может повлиять на ваши игровые данные_.",
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/lv":
        case "/lv{$bl->botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => $bl->getLevelMessage($message[1]),
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/link":
        case "/link{$bl->botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => $bl->linkAccount($data["message"]),
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/unlink":
        case "/unlink{$bl->botUserName}":
            require_once "../../incl/lib/connection.php";

            $accQuery = $db->prepare("SELECT accID FROM tg_users WHERE userID LIKE :uID");
            $accQuery->execute([":uID" => $data["message"]["from"]["id"]]);

            if ($accQuery->rowCount() == 0) {
                $method = "sendMessage";
                $send_data = [
                    "chat_id" => $data["message"]["chat"]["id"],
                    "text" => "*❌ Вы не привязывали аккаунт*",
                    "parse_mode" => "Markdown",
                    "reply_to_message_id" => $data["message"]["message_id"]
                ];
                break;
            }
            # Если нашёл это, то напиши "123434" в чат RusDash ;)

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
        case "/lvedit{$bl->botUserName}":
            require_once "../../incl/lib/connection.php";
            require_once "../../incl/lib/mainLib.php";

            $accQuery = $db->prepare("SELECT * FROM tg_users WHERE userID LIKE :uID");
            $accQuery->execute([":uID" => $data["message"]["from"]["id"]]);

            if ($accQuery->rowCount() == 0) {
                $method = "sendMessage";
                $send_data = [
                    "chat_id" => $data["message"]["chat"]["id"],
                    "text" => "*❌ Вы не привязали аккаунт!*",
                    "parse_mode" => "Markdown",
                    "reply_to_message_id" => $data["message"]["message_id"]
                ];
                break;
            }

            if (empty($message[1])) {
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

            if ($lvQuery->rowCount() == 0) {
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

            $levelMessage = $bl->getLevelMessage($lvData["levelID"]);

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
                "text" => "✏️ $levelMessage",
                "reply_markup" => $markup,
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/daily":
        case "/daily{$bl->botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => $bl->getPromotedLevel("0"),
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/weekly":
        case "/weekly{$bl->botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => $bl->getPromotedLevel("1"),
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/event":
        case "/event{$bl->botUserName}":
            $method = "sendMessage";
            $send_data = [
                "chat_id" => $data["message"]["chat"]["id"],
                "text" => $bl->getPromotedLevel("2"),
                "parse_mode" => "Markdown",
                "reply_to_message_id" => $data["message"]["message_id"]
            ];
            break;

        case "/ticket":
        case "/ticket{$bl->botUserName}":
            require_once "../../incl/lib/connection.php";

            if (str_starts_with($data["message"]["chat"]["id"], "-")) {
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

            if ($accQuery->rowCount() == 0) {
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

            if ($stateData[0] == 3) {
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

    $bl->sendTelegramQuery($method, $send_data);
}