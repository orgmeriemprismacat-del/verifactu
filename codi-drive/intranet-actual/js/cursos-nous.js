var urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let hashUrl = null;
if (window.location.hash.split('#')[1])
   hashUrl = window.location.hash.split('#')[1].split('/')[1];

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
$(document).bind("ajaxSend", function() {
   mostrarModalLoading();
}).bind("ajaxComplete", function() {
   amagarLoadingModal();
});

/* Consulta el codi del main */
var requestMain = $.ajax({
   url: path + "mostrarMain.php",
   method: "GET",
   data: {
      url: urlPagina
   },
   dataType: "html"
});

var eventsConst = [];
var events = [];
let colorsEvents = [
   '',
   'Lavender',
   'Green',
   'Morat',
   'RosaXiclet',
   'Platan',
   'Orange',
   'Azure',
   'Grafit',
   'Nabiu',
   'Alfebrega',
   'Red'
]
let colorsCalendars = [
   '',
   'Cacau',
   'RosaXiclet',
   'Red',
   'Orange',
   'Carbassa',
   'Mango',
   'Eucaliputs',
   'Alfebrega',
   'Pistatxo',
   'Advocat',
   'GrocVerdos',
   'Platan',
   'Green',
   'Azure',
   'BlauCobalt',
   'Nabiu',
   'Lavender',
   'Malva',
   'Grafit',
   'GrisVerdos',
   'Scarlet',
   'Cherry',
   'Morat',
   'Purple'
]

requestMain.done(function(message) {
   $('.mainpanel').html(message);
   mostrarModalLoading();

   var calendarEl = document.getElementById('calendar');

   var dateNow = new Date();
   // var initialDate = "2022-02-07";
   var monthDate = dateNow.getMonth() + 1;
   if (monthDate < 10) {
      monthDate = "0" + monthDate;
   }
   var initialDate = dateNow.getFullYear() + "-" + monthDate + "-" + dateNow.getDate();
   console.log(initialDate);

   var textCalendaris = $('#regCalendaris').html().trim();
   $('#regCalendaris').remove();

   console.log(textCalendaris);
   var regCalendaris = textCalendaris.split('###');
   for (var i = 1; i < regCalendaris.length; i++) {
      var textEventRegCalendaris = regCalendaris[i];
      var infoEvent = textEventRegCalendaris.split('$$$');

      // var infoEvent = regEventCalendaris[i];
      var eventID = infoEvent[0];
      var titolEvent = infoEvent[1];
      var startEvent = infoEvent[2];
      var endEvent = infoEvent[3];
      var colorCalendarEventId = infoEvent[4];
      var colorEventId = infoEvent[5];
      var calendarId = infoEvent[6];

      // colorEvent = colors[colorEventId];
      console.log('title:' + titolEvent);
      console.log('color Event Id:' + colorEventId);
      console.log('color Calendar Id:' + colorCalendarEventId);

      if (colorEventId == '') colorEvent = colorsCalendars[colorCalendarEventId];
      else colorEvent = colorsEvents[colorEventId];

      var classEvent = 'eventType' + colorEvent;

      eventsConst[i - 1] = {
         title: titolEvent,
         start: startEvent,
         end: endEvent,
         eventID: eventID,
         calendarId: calendarId,
         className: [classEvent]
      }
      // eventsConst[i-1] = {
      // 	title: titolEvent,
      // 	start: startEvent,
      // 	end: endEvent,
      // 	className: [classEvent],
      // 	url: 'https://www.prisma.cat'
      // }
   }

   var now = new Date();

   var calendar = new FullCalendar.Calendar(calendarEl, {
      themeSystem: 'bootstrap',
      locale: 'ca',
      showNonCurrentDates: false,
      nowIndicator: true,
      now: now.toISOString(),
      bootstrapFontAwesome: {
         close: 'fa-times',
         prev: 'fa-solid fa-chevron-left',
         next: 'fa-solid fa-chevron-right',
         prevYear: 'fa-angle-double-left',
         nextYear: 'fa-angle-double-right'
      },
      initialView: 'dayGridMonth',
      initialDate: initialDate,
      headerToolbar: {
         left: 'dayGridMonth,timeGridWeek,timeGridDay,listYear',
         center: 'title',
         right: 'today prev,next'
      },
      events: eventsConst,
      dateClick: function(info) {
         alert('Clicked on: ' + info.dateStr);
         alert('Coordinates: ' + info.jsEvent.pageX + ',' + info.jsEvent.pageY);
         alert('Current view: ' + info.view.type);
         // change the day's background color just for fun
         // info.dayEl.style.backgroundColor = 'red';
      },
      eventClick: function(info) {
         info.jsEvent.preventDefault(); // don't let the browser navigate

         visualitzarEvent(info);
      },
      eventDrop: function(info) {
         moureEvent(info)
      },
      editable: true,
      selectable: true
   });

   calendar.render();

   amagarLoadingModal();

   function visualitzarEvent(info) {
      alert('Event: ' + info.event.title);
      alert('Coordinates: ' + info.jsEvent.pageX + ',' + info.jsEvent.pageY);
      alert('View: ' + info.view.type);

      if (info.event.url) {
         alert(info.event.title);
         window.open(info.event.url);
      }

      /*
		  	Crida PHP per visualitzar el evento.

			Fer crida per visualitzar un modal d'un evento amb l'id de l'evento, l'id del calendari.
		  */
   }

   function moureEvent(info) {
      alert(info.event.title + " was dropped on " + info.event.start.toISOString());
      if (!confirm("Are you sure about this change?")) {
         info.revert();
      } else {
         /*
				  Crida PHP per realitzar el canvi del event.
				  Per fer els canvis són necessaris el Id del event, el id del calendari i la nova data
			  */
      }
   }
});

requestMain.fail(function(jqXHR, textStatus, errorThrown) {
   errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: ");
});
