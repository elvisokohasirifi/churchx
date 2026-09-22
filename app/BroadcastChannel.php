<?php

namespace App;

enum BroadcastChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Push = 'push';
    case WhatsApp = 'whatsapp';
}
