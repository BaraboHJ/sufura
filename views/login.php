<?php
use App\Core\Csrf;
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="bg-white p-4 rounded shadow-sm">
            <h1 class="h5 mb-3">Sign in</h1>
            <form method="post" action="/?r=login">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>
