<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="calendar.css">
    <title>Ethiopian Calendar-js v1.0.1 | Gregorian Calendar to Ethiopian Calendar Converter</title>
</head>

<body>
    <div class="nav">
        <div class="flex">
            <div>
                <h3 class="title">Ethiopian Calendar-js v1.0.1</h3>
            </div>
            <div>
                <ul>
                    <li class="drop-down">
                        <a href="#" class="drop-down-btn">About</a>
                        <div class="drop-down-content">
                            <div style="display: inline">
                                <p>Thank you for using !!!</p>
                                <hr>
                                <div><b>Ethiopian Calendar</b></div>
                                <div><small>Github - <a href="#">Ethiopian Calendar</a></small></div>
                            </div>
                            <hr>
                            <div style="display: inline">
                                <div><img src="external assets/img/ms.png" alt="MSystems" width="100" height="100"
                                        style="border-radius: 50%"></div>
                                <div><b>MIKEINTOSH SYSTEMS</b></div>
                                <div><small>Website - <a
                                            href="#">MIKEINTOSH SYSTEMS</a></small>
                                </div>
                            </div>
                            <hr>
                            <b><small>&copy; MIKEINTOSH SYSTEMS</small></b>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="content">
        <div>
            Only two files are required to use the converter
            <ul>
                <li><b>calendar.js</b> : contains javascript code basis for conversion,</li>
                <li><b>calendar.css</b> : styles for the table.</li>
            </ul>
            <pre>...
&lt;<span class="tag">link</span> <span class="attr">rel</span>=<span class="string">"stylesheet"</span> <span class="attr">href</span>=<span class="string">"/modules/calendar/calendar.css"</span>&gt;
...
&lt;<span class="red">script</span> <span class="attr">src</span>=<span class="string">"/modules/calendar/calendar.js"</span>&gt;&lt;/<span class="red">script</span>&gt;
&lt;<span class="red">script</span>&gt;

<span class="comment">// Use the following code to use the converter</span>
<span class="variable"><span class="keyword">var</span> EC</span> = <span class="keyword">new</span> <span class="method">EthiopianCalendar</span>(<span class="keyword">new</span> <span class="method">Date</span>()); <span class="comment">// set current GC date</span>
<span class="comment">//EC = new EthiopianCalendar(new Date(...)); // custom date</span>

document.<span class="method">getElementsByClassName</span>(...)[0].innerHTML = <span class="variable">EC</span>.<span class="method">ECDrawCalendar</span>(); <span class="comment">// draw Ethiopian Calendar table</span>
document.<span class="method">getElementsByClassName</span>(...)[0].innerHTML = <span class="variable">EC</span>.<span class="method">GCDrawCalendar</span>(); <span class="comment">// draw Gregorian Calendar table</span>

<span class="comment">// EC.GetLeapYear(); // returns leap year 5 or 6</span>
<span class="comment">// EC.GetECDate(String format); // get Ethiopian Calendar date with custom date format (Y-y-M-m-D-d)</span>
<span class="comment">// EC.GetECMonthLength(); // returns 30 for others, 5 or 6 for leap year</span>
<span class="comment">// EC.GetGCDate(String format); // get Gregorian Calendar date with custom date format...</span>
<span class="comment">// EC.GetGCMonthFullName(); // returns full name of Gregorian Calendar month</span>
<span class="comment">// EC.GetGCDayFullName(); // returns full name of Gregorian Calendar day</span>
<span class="comment">// EC.GetGCMonthLength(); // returns length of Gregorian Calendar month</span>
&lt;/<span class="red">script</span>&gt;</pre>
        </div>
    </div>
    <script src="calendar.js"></script>
    <script>
        var EC = new EthiopianCalendar(new Date());
        //alert(EC.GetECDate("Y(y)-M(m)-D(d)") + " " + EC.GetGCDate("Y(y)-M(m)-D(d)"));
        document.getElementsByClassName("content")[0].innerHTML += (EC.ECDrawCalendar() + EC.GCDrawCalendar());
    </script>
</body>

</html>