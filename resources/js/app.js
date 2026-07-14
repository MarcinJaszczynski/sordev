import "./bootstrap";
import { initProgramPointTreeDnD } from "./program-point-tree-dnd.js";
import "./theme-switcher.js";

window.initProgramPointTreeDnD = initProgramPointTreeDnD;

// small defensive fixes for front-end (mobile cookie/manage button, scroll-top duplicates)
import "./front-fixes.js";
