<?php
declare(strict_types=1);

namespace AP\Modules\Money\Import;

/** What the Gmail import needs from a mailbox. The real one talks IMAP; tests use a small fake. */
interface Mailbox
{
    /** @return list<int> ids of mails from the last N days, newest last */
    public function recent(int $days, int $limit): array;

    /** The header lines only (From, Subject, Date, Message-ID). */
    public function header(int $id): string;

    /** The whole raw email. */
    public function raw(int $id): string;

    /** Move the email to the bin. */
    public function trash(int $id): bool;

    public function close(): void;
}
