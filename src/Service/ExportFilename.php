<?php

namespace App\Service;

enum ExportFilename: string {
    case FileShare = 'file_share';
    case CalendarShare = 'calendar_share';
    case DeckShare = 'deck_share';
}
