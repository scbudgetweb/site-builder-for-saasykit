<?php

namespace ScBudgetWeb\SiteBuilder\MailingLists;

use RuntimeException;

/**
 * A mailing list service refused a request or couldn't be reached. The message is for admins (the log, the API key
 * check); visitors see a general apology.
 */
class MailingListException extends RuntimeException {}
