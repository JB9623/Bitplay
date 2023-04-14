var Dashboard = function() {
    return {
        	initCalendar: function() {
            if (jQuery().fullCalendar) {
                var e = new Date,
                    t = e.getDate(),
                    a = e.getMonth(),
                    i = e.getFullYear(),
                    l = {};
                $("#calendar").width() <= 400 ? ($("#calendar").addClass("mobile"), l = {
                    left: "title, prev, next",
                    center: "",
                    right: "today,month,agendaWeek,agendaDay"
                }) : ($("#calendar").removeClass("mobile"), l = App.isRTL() ? {
                    right: "title",
                    center: "",
                    left: "prev,next,today,month,agendaWeek,agendaDay"
                } : {
                    left: "title",
                    center: "",
                    right: "prev,next,today,month,agendaWeek,agendaDay"
                }), $("#calendar").fullCalendar("destroy"), $("#calendar").fullCalendar({
                    disableDragging: !1,
                    header: l,
                    editable: !0,
                    events: [{
                        title: "All Day",
                        start: new Date(i, a, 1),
                        backgroundColor: App.getBrandColor("yellow")
                    }, {
                        title: "Long Event",
                        start: new Date(i, a, t - 5),
                        end: new Date(i, a, t - 2),
                        backgroundColor: App.getBrandColor("blue")
                    }, {
                        title: "Repeating Event",
                        start: new Date(i, a, t - 3, 16, 0),
                        allDay: !1,
                        backgroundColor: App.getBrandColor("red")
                    }, {
                        title: "Repeating Event",
                        start: new Date(i, a, t + 6, 16, 0),
                        allDay: !1,
                        backgroundColor: App.getBrandColor("green")
                    }, {
                        title: "Meeting",
                        start: new Date(i, a, t + 9, 10, 30),
                        allDay: !1
                    }, {
                        title: "Lunch",
                        start: new Date(i, a, t, 14, 0),
                        end: new Date(i, a, t, 14, 0),
                        backgroundColor: App.getBrandColor("grey"),
                        allDay: !1
                    }, {
                        title: "Birthday",
                        start: new Date(i, a, t + 1, 19, 0),
                        end: new Date(i, a, t + 1, 22, 30),
                        backgroundColor: App.getBrandColor("purple"),
                        allDay: !1
                    }, {
                        title: "Click for Google",
                        start: new Date(i, a, 28),
                        end: new Date(i, a, 29),
                        backgroundColor: App.getBrandColor("yellow"),
                        url: "http://google.com/"
                    }]
                })
            }
        },
        init: function() {
            this.initCalendar()
        }
    }
}();
App.isAngularJsApp() === !1 && jQuery(document).ready(function() {
    Dashboard.init()
});