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
                    right: "month,agendaWeek,agendaDay"
                }) : ($("#calendar").removeClass("mobile"), l = App.isRTL() ? {
                    right: "title",
                    center: "",
                    left: "prev,next,month,agendaWeek,agendaDay"
                } : {
                    left: "title",
                    center: "",
                    right: "prev,next,month,agendaWeek,agendaDay"
                }), $("#calendar").fullCalendar("destroy"),
                $("#calendar").fullCalendar({
                    disableDragging: !1,
                    // dateFormat: 'YYYY-MM-DD  HH:mm:ss',
                    header: l,
                    id: 'calendar',
                    // defaultView: 'basicWeek',
                    editable: !0,
                    events:[],
                    select: function(start, end,jsEvent, view) {
                        //var title = prompt('Event Title:');
                        // console.log(jsEvent);
                        // console.log(this.calendar.options.id);
                            var startDate = start.format('YYYY-MM-DD');
                            var startTime = start.format('HH:mm');
                            var endTime = end.format('HH:mm');
                            $('#form_date').val(startDate);
                            $('#from_time').val(startTime);
                            $('#to_time').val(endTime);

                            $("#add_appointment").modal('show');
                          //  console.log(start.format('YYYY-MM-DD HH:mm:ss'));

                        //     $(this.calendar.options.id).fullCalendar('renderEvent', eventData, true); // stick? = true
                        // $(this.calendar.options.id).fullCalendar('unselect');
                    },
                    // selectOverlap: function(event) {
                    //     return ! event.block;
                    // },
                    displayEventEnd: {
                      month: true,
                      basicWeek: true,
                      "default": true
                    },
                selectable: true,
         selectHelper: true,
         navLinks: true,
         //selectConstraint: 'businessHours',
         // eventConstraint: 'businessHours',
         //eventLimit: true,
         //editable: true,
         allDaySlot: false,
         eventDrop: function(event,dayDelta,minuteDelta,allDay,revertFunc) {
           if(event.sch_id){
               var sch_id = (event.sch_id);
               change_date_time_event(sch_id,dayDelta/1000);
            }
            else if(event.leave) {
              var leave = (event.leave);
              change_date_time_leave(leave,dayDelta/1000);
            }
            else{
              alert("You cant change !!!");
              revertFunc();
            }
        },
                    eventDataTransform: function(eventData){
                      if(eventData.title)
                            eventData.editable = true;
                        return eventData;
                    },
                    eventRender: function (event, element) {

                        if (event.customer) {
                            element.find("div.fc-content").prepend('<b>'+event.customer+'</b>');
                        }
                        element.attr('href', 'javascript:void(0);');
                        if(event.cpr_no){
                          element.qtip({
                          content:'<b>' + moment(event.start).format('Do-MMM-YYYY HH:mm') + ' - ' + moment(event.end).format('HH:mm') + '</b>' +
                          '<br>' +
                          ' Customer :- '+ event.customer +' , CPR No :-' + event.cpr_no + '<br>'+
                          'Mobile No :- '+ event.mobile
                          });
                        }
                      // console.log('event = '+JSON.stringify(event));
                      
                        element.click(function() {
                          //console.log(element);
                          if(event.cus_status && event.cus_status=="Completed"){
                            window.open(event.complete,'_blank');
                            console.log("cool");
                            return false;
                          }
                          if(event.description)
                              $('#event_title').html('Appointment Details');
                          else
                              $('#event_title').html('User Activities');
                            var start = moment(event.start).format('Do-MMM-YYYY HH:mm:ss');
                            var end = moment(event.end).format('Do-MMM-YYYY HH:mm:ss');
                           // console.log(event.end);
                            var event_data='';
                            if(event.title)
                              event_data = '<tr><th>Title</th><td>'+event.title+'</td></tr>';
                            if(event.description){
                              if(event.customer)
                                event_data += "<tr><th>Customer</th><td>"+event.customer+"</td></tr>";
                              event_data += '<tr><th>Description</th><td>'+event.description+'</td></tr>';
                            }
                            event_data += '<tr><th>Start</th><td>'+start+'</td></tr>';
                            if(event.end)
                              event_data += '<tr><th>End</th><td>'+end+'</td></tr>';
                            //event_data += '<tr><th>Status</th><td>'+event.status+'</td></tr>';
                            event_data += '<tr><th>Action</th><td><a href="'+event.del_url+'">Delete</a></td></tr>';
                            if(event.cus_status){
                              event_data += '<tr><th>Current Status</th><td><select class="form-control" onchange="change_cur_status('+event.sch_id+',this.value)">\
                              <option value=""> Select Current Status  </option>\
                              <option value="Booked"';if(event.cus_status=="Booked") event_data +='selected'; event_data +='> Booked </option>\
                              <option value="Arrived"';if(event.cus_status=="Arrived") event_data +='selected'; event_data +='> Arrived </option>\
                              <option value="Inprocess"';if(event.cus_status=="Inprocess") event_data +='selected'; event_data +='> Inprocess </option>\
                              <option value="Completed"';if(event.cus_status=="Completed") event_data +='selected'; event_data +='> Completed </option>\
                              <option value="No"';if(event.cus_status=="No") event_data +='selected'; event_data +='> No Show </option>\
                              </select></td></tr>';
                            }
                            if(event.users)
                              event_data += '<tr><th>See Users</th><td><a class="user" data-toggle="modal" id="'+event.users+'" onclick="show_user(this.id)" href="">Show users</a></td></tr>';
                            if(event.url)
                              event_data += '<tr><th>Edit</th><td><a href="'+event.url+'">Edit</a></td></tr>';
                            // if(event.sendEmail)
                            //   event_data += '<tr><th>Send Email</th><td><a href="'+event.sendEmail+'" target="_blank">Send Email</a></td></tr>';
                            $("#table_event").html(event_data);
                            if(event.sch_id){
                              edit_appointment(event.sch_id,event,'calendar');
                            }
                            else
                              $("#eventContent").modal('show');
                            //console.log(event);
                        });
                       //  if(event.description && event.url){
                       //    var e = element
                       //      .prepend("<div style='width:10px;' class='closeon' onclick='delete_app("+event.sch_id+")'>&#10005;</div>");
                       // }
                    }
                })
            }
        },
        init: function() {
           // this.initCalendar()
        }
    }
}();
App.isAngularJsApp() === !1 && jQuery(document).ready(function() {
    Dashboard.init()
});
