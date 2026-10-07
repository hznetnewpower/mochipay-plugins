<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(){require_once __DIR__.'/../../lib/bootstrap.php';\MochiPayShared\Store::pdo(DB::connection()->getPdo(),DB::getTablePrefix().'mochipay_attempt')->install();}
    public function down(){/* Payment history is intentionally retained. */}
};
