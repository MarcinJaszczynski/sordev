import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import plLocale from '@fullcalendar/core/locales/pl';

window.SorFullCalendar = Calendar;
window.SorFullCalendarPlugins = {
    dayGrid: dayGridPlugin,
    list: listPlugin,
    timeGrid: timeGridPlugin,
    interaction: interactionPlugin,
};
window.SorFullCalendarLocalePl = plLocale;

window.initSorFullCalendar = function (elementId, events = [], options = {}) {
    const el = document.getElementById(elementId);
    if (!el || typeof window.SorFullCalendar === 'undefined') {
        return null;
    }

    const calendar = new window.SorFullCalendar(el, {
        plugins: [
            window.SorFullCalendarPlugins.dayGrid,
            window.SorFullCalendarPlugins.list,
            window.SorFullCalendarPlugins.interaction,
        ],
        initialView: options.initialView || 'dayGridMonth',
        locale: window.SorFullCalendarLocalePl,
        height: options.height || 'auto',
        headerToolbar: options.headerToolbar || {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth',
        },
        events,
        ...options,
    });

    calendar.render();

    return calendar;
};
