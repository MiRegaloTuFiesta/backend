<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // Migrar notas antiguas a la tabla de mensajes de soporte (general por creador, adjuntando el evento)
        $events = DB::table('events')->whereNotNull('admin_notes')->where('admin_notes', '!=', '')->get();
        foreach ($events as $event) {
            $senderId = $event->assigned_admin_id;
            if (!$senderId) {
                // Buscar primer administrador
                $senderId = DB::table('users')->where('role', 'admin')->value('id');
            }
            if (!$senderId) {
                // Último recurso: el creador del evento
                $senderId = $event->user_id;
            }

            DB::table('support_messages')->insert([
                'user_id' => $event->user_id,
                'sender_id' => $senderId,
                'event_id' => $event->id,
                'message' => $event->admin_notes,
                'is_read' => true,
                'created_at' => $event->updated_at ?? now(),
                'updated_at' => $event->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
