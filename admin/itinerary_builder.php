<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Itinerary Builder</title>

    <?php include 'includes/header-links.php'; ?>

    <!-- QUILL EDITOR -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <style>
        .top-tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #eee;
        }

        .top-tabs span {
            padding: 10px;
            cursor: pointer;
        }

        .top-tabs .active {
            border-bottom: 3px solid #4e73df;
            font-weight: 600;
        }

        .cover {
            height: 180px;
            background: url('https://via.placeholder.com/1200x200') center/cover;
            border-radius: 6px;
            position: relative;
            color: #fff;
            display: flex;
            align-items: flex-end;
            padding: 20px;
        }

        .change-cover {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.5);
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
        }

        .builder-wrapper {
            display: flex;
            height: calc(100vh - 250px);
        }

        .left-panel {
            width: 220px;
            border-right: 1px solid #ddd;
        }

        .day-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .day-item.active {
            background: #d8ecf7;
            font-weight: 600;
        }

        .center-panel {
            flex: 1;
            padding: 15px;
        }

        .right-panel {
            width: 300px;
            border-left: 1px solid #ddd;
            padding: 10px;
        }

        .btn-blue {
            background: #4e73df;
            color: #fff;
            border-radius: 6px;
            padding: 6px 12px;
        }

        .editor-box {
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-top: 10px;
        }

        .event-menu {
            position: absolute;
            top: 40px;
            right: 0;
            width: 220px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            z-index: 999;
        }

        .event-menu div {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-menu div:hover {
            background: #f5f7fa;
        }

        .event-menu div:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include 'includes/top-header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <section class="content">
                <div class="container-fluid">

                    <!-- TABS -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="top-tabs">
                            <span class="active">Build</span>
                            <span>Pricing</span>
                            <span>Final</span>
                        </div>
                    </div>

                    <!-- COVER -->
                    <div class="cover mt-2" id="coverDiv">
                        <input type="file" id="coverInput" hidden>
                        <div class="change-cover" onclick="document.getElementById('coverInput').click()">
                            📷 Change Cover Photo
                        </div>
                        <h4>sikkim ✏️</h4>
                    </div>

                    <!-- MAIN -->
                    <div class="builder-wrapper mt-2">

                        <!-- LEFT -->
                        <div class="left-panel" id="dayList"></div>

                        <!-- CENTER -->
                        <div class="center-panel">

                            <div class="d-flex justify-content-between">
                                <h5 id="dayTitle">Day 1</h5>
                                <div class="position-relative">

                                    <button class="btn btn-blue btn-sm" onclick="toggleEventMenu()">
                                        + New Event
                                    </button>

                                    <div id="eventMenu" class="event-menu d-none">
                                        <div onclick="selectEvent('Accommodation')"><i class="fas fa-bed"></i>
                                            Accommodation</div>
                                        <div onclick="selectEvent('Activity')"><i class="fas fa-image"></i> Activity
                                        </div>
                                        <div onclick="selectEvent('Transportation')"><i class="fas fa-car"></i>
                                            Transportation</div>
                                        <div onclick="selectEvent('Visa')"><i class="fas fa-id-card"></i> Visa</div>
                                        <div onclick="selectEvent('Meal')"><i class="fas fa-utensils"></i> Meal</div>
                                        <div onclick="selectEvent('Flight')"><i class="fas fa-plane"></i> Flight</div>
                                        <div onclick="selectEvent('Leisure')"><i class="fas fa-walking"></i> Leisure
                                        </div>
                                        <div onclick="selectEvent('Cruise')"><i class="fas fa-ship"></i> Cruise</div>
                                    </div>

                                </div>
                            </div>

                            <!-- RICH EDITOR -->
                            <div id="editor" class="editor-box" style="height:200px;"></div>

                        </div>

                        <!-- RIGHT -->
                        <div class="right-panel">
                            <input type="text" class="form-control mb-2" placeholder="Search">
                            <select class="form-control">
                                <option>Day Itinerary</option>
                            </select>
                        </div>

                    </div>

                </div>
            </section>
        </div>

        <?php include 'includes/footer-links.php'; ?>

        <!-- QUILL -->
        <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

        <script>
            /* =========================
               DAY DATA (Frontend State)
            ========================= */
            let days = [
                { title: "Day 1", content: "" },
                { title: "Day 2", content: "" },
                { title: "Day 3", content: "" },
                { title: "Day 4", content: "" },
                { title: "Day 5", content: "" },
                { title: "Day 6", content: "" }
            ];

            let currentDay = 0;

            /* =========================
               INIT QUILL
            ========================= */
            let quill = new Quill('#editor', {
                theme: 'snow'
            });

            /* =========================
               RENDER DAYS
            ========================= */
            function renderDays() {
                let html = '';
                days.forEach((day, index) => {
                    html += `
        <div class="day-item ${index === currentDay ? 'active' : ''}" onclick="selectDay(${index})">
            ${day.title}
        </div>`;
                });
                document.getElementById('dayList').innerHTML = html;
            }

            /* =========================
               SELECT DAY
            ========================= */
            function selectDay(index) {

                // save previous content
                days[currentDay].content = quill.root.innerHTML;

                currentDay = index;

                // update title
                document.getElementById('dayTitle').innerText = days[index].title;

                // load content
                quill.root.innerHTML = days[index].content || "";

                renderDays();
            }

            /* =========================
               COVER IMAGE CHANGE
            ========================= */
            document.getElementById('coverInput').addEventListener('change', function (e) {
                let file = e.target.files[0];
                if (file) {
                    let reader = new FileReader();
                    reader.onload = function (e) {
                        document.getElementById('coverDiv').style.backgroundImage =
                            `url(${e.target.result})`;
                    }
                    reader.readAsDataURL(file);
                }
            });

            /* INIT */
            renderDays();
        </script>

        <script>
            function toggleEventMenu() {
                document.getElementById('eventMenu').classList.toggle('d-none');
            }

            // Close on outside click
            document.addEventListener('click', function (e) {
                const menu = document.getElementById('eventMenu');
                if (!e.target.closest('.position-relative')) {
                    menu.classList.add('d-none');
                }
            });

            // Click action
            function selectEvent(type) {
                alert(type + " selected"); // replace later with real UI
                document.getElementById('eventMenu').classList.add('d-none');
            }
        </script>

    </div>
</body>

</html>