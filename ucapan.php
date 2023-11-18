<?php
    require __DIR__ . '/vendor/autoload.php';

    if (getenv('APP_ENV') == 'production') {
        error_reporting(0);
        @ini_set('display_errors', 0);
    } else {
        error_reporting(1);
        @ini_set('display_errors', 1);
    }

    //load the environment variablea
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();

    $storage = new Flatbase\Storage\Filesystem('storage');
    $flatbase = new Flatbase\Flatbase($storage);

    session_start();
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levidio Wedding Vol 2 - Website Templates</title>

    <!-- CSS  -->
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <!-- Root-Icon -->
    <link rel="stylesheet" href="https://cdn.rootpixel.net/assets/rooticon/v2/rooticon.css">
    <!-- Datatable -->
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" type="text/css">
    <!-- App -->
    <link rel="stylesheet" href="_assets/css/app.min.css">
    <!-- Override Datatable style -->
    <style>
        .dataTable-wrapper .dataTable-top {
            margin-bottom: 2.4rem;
        }
        .dataTable-table thead tr th {
            padding-right: 20px;
        }
        .dataTable-table thead tr th a.dataTable-sorter::after,.dataTable-table thead tr th a.dataTable-sorter::before {
            right: -10px;
        }
        .dataTable-table tbody tr td {
            padding: 2rem 20px 2rem 1rem !important;
        }
        .dataTable-table tbody tr td:not(:last-child) {
            padding: 2rem 50px 2rem 1rem !important;
        }
        .dataTable-table tbody tr:last-child td {
            border-bottom: unset;
        }
    </style>
</head>
<body>
    <div class="container py-10 pb-6">
        <h2 class="font-type-secondary font-bold mb-8">Data Ucapan</h2>

        <div class="table-responsive mb-12">
            <table id="datatable" class="table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Attendance</th>
                        <th>Greeting</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        // check the amount of your rsvp
                        $rsvp = $flatbase->read()->in('rsvp')->sortDesc('created_at');
                        foreach ($rsvp->get() as $rsvp) {
                            $greeting=$flatbase->read()->in('greetings')->where('rsvp_id','==',$rsvp['id']);
                    ?>
                            <tr>
                                <td><?= ++$num;?></td>
                                <td><?= $rsvp['name'];?></td>
                                <td><?= $rsvp['phone'];?></td>
                                <td><?= ($rsvp['attendance']==1) ? "Hadir" : "Tidak Hadir";?></td>
                                <td><?= ($greeting->count() > 0) ? $greeting->first()['greeting'] : "-";?></td>
                                <td>
                                    <?php if($greeting->count() > 0) { ?>
                                        <form method="POST" action="core.php">
                                            <input type="hidden" name="method_field" value="DELETE">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="id" value="<?= $greeting->first()['id'] ?>">
                                            <button onclick="handleDeleteRow(this)" type="button" class="btn btn-xs btn-danger d-flex align-items-center">
                                                <i class="ri ri-trash mr-lg-1"></i> 
                                                <span class="d-none d-lg-inline">Ucapan</span>
                                            </button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                    <?php
                        }
                    ?>
                </tbody>
            </table>
        </div>

        <p class="text-dark text-center mt-10">© 2021 Levidio Wedding Vol 2 | <a href="https://levidio.id/wedding/vol2/" target="_blank">www.levidio.id/wedding/vol2</a></p>
    </div>
    <!-- JS -->
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
    <!-- Datatable -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" type="text/javascript"></script>
    <!-- App -->
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Handle Init Datatable
        // let initData = {
        //     'headings': ['No', 'Nama', 'HP/WA', 'Kehadiran', 'Ucapan', 'Aksi'],
        //     'data': []
        // };
        // const kehadiranData = ['Ya', 'Tidak'];
        // const dataTable = new simpleDatatables.DataTable("#datatable", {
        //     fixedHeight: false,
        //     columns: [
        //         { 
        //             select: 5, sortable: false,
        //             render: function(data, cell, row) {
        //                 cell.style.paddingRight = 0;
        //                 cell.classList.add('text-end');
        //                 return `<a onclick="handleDeleteRow(event)" href="http://localhost/messages/${data}/delete" 
        //                             class="btn btn-xs btn-danger d-flex align-items-center">
        //                                 <i class="ri ri-trash mr-lg-1"></i> 
        //                                 <span class="d-none d-lg-inline">Ucapan</span>
        //                         </a>`;
        //             }
        //         },
        //     ]
        // });
        const dataTable = new simpleDatatables.DataTable("#datatable");

        // Handle insert data to datatable
        // ( async() => {
        //     await fetch('https://jsonplaceholder.typicode.com/comments')
        //         .then(response => response.json())
        //         .then(result => {
        //             result.map((value,index) => {
        //                 initData.data.push([
        //                     (++index).toString(),
        //                     value.name,
        //                     '085123457689',
        //                     kehadiranData[ Math.floor(Math.random() * kehadiranData.length) ],
        //                     value.body,
        //                     value.id.toString(),
        //                 ])
        //             })
        //         });
        //     dataTable.insert(initData);
        // } )();

        // Handle on delete action row
        const handleDeleteRow = (event) => {
            Swal.fire({
                icon: 'warning',
                title: 'Hapus ucapan ini ?',
                showCancelButton: true,
                confirmButtonText: `Iya, Hapus`,
                cancelButtonText: `Batal`,
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isConfirmed) {
                    Swal.fire('Ucapan Berhasil Dihapus!', '', 'success')
                    event.closest("form").submit();
                }
            })
        }

    </script>
</body>
</html>