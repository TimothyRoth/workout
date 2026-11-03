/**
 * Entry module. Module scripts run after the DOM is parsed, so no
 * DOMContentLoaded wrapper is needed. Other modules are resolved through
 * the import map in the layout, which versions them like this file.
 */

import {initDialogs, initConfirmations} from "app/dialogs";
import {initSession} from "app/session";
import {initUnitSelects} from "app/forms";

initDialogs();
initConfirmations();
initUnitSelects();
initSession();
