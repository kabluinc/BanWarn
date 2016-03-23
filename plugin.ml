name: WarnBan
api: [1.13.0]
version: 1.4.3
load: POSTWORLD
main: robske_110\WarnBan\Main
author: robske_110
website: https://github.com/robske110/BanWarn

commands:
 warn:
  description: Warnt einen Spieler
  usage: "/warn <SpielerName> <Grund> <Punkte>"
  permission: ServerCore.command.warn
 warninfo:
  description: Gibt Infos über die Warnungspunkte eines Spielers und die Gründe
  usage: "/warninfo <SpielerName>"
  permission: ServerCore.command.warninfo
  aliases: [wi]

permissions:
 ServerCore.command.warn:
  description: "Allows the user to run the warn command"
  default: op
ServerCore.command.warninfo:
  description: "Allows the user to run the warninfo command"
  default: op