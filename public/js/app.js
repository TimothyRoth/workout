/**
 * Entry module. Module scripts run after the DOM is parsed, so no
 * DOMContentLoaded wrapper is needed. Other modules are resolved through
 * the import map in the layout, which versions them like this file.
 */

import {initDialogs, initConfirmations} from "app/dialogs";
import {initSession} from "app/session";
import {initUnitSelects, initPasswordToggles} from "app/forms";
import {initSteppers} from "app/stepper";
import {initPwa} from "app/pwa";
import {initExercises} from "app/exercises";

initDialogs();
initConfirmations();
initUnitSelects();
initPasswordToggles();
initSteppers();
initExercises();
initSession();
initPwa();
