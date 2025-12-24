<?php
// Navbar / Sidebar menu
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">CCAMS for VATSIM</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='index.php'){echo 'active';} ?>" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='codes.php'){echo 'active';} ?>" href="codes.php">Config</a></li>
        <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='map.php'){echo 'active';} ?>" href="map.php">Mode S Map</a></li>
        <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='about.php'){echo 'active';} ?>" href="about.php">About</a></li>
      </ul>
    </div>
  </div>
</nav>
