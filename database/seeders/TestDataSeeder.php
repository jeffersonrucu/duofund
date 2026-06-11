<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Family;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    /**
     * Popula dados de teste para a conta test@example.com.
     * Idempotente: limpa os dados do casal antes de recriar.
     */
    public function run(): void
    {
        // --- Família (casal) para testar escopo "shared" ---
        $family = Family::firstOrCreate(['id' => 1]);

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'email_verified_at' => now()]
        );
        $user->update(['family_id' => $family->id]);

        $partner = User::firstOrCreate(
            ['email' => 'parceiro@example.com'],
            ['name' => 'Maria', 'password' => 'password', 'email_verified_at' => now(), 'family_id' => $family->id]
        );
        $partner->update(['family_id' => $family->id]);

        $userIds = [$user->id, $partner->id];

        // --- Limpa dados antigos do casal ---
        Transaction::whereIn('user_id', $userIds)->delete();
        Category::whereIn('user_id', $userIds)->delete();
        Goal::whereIn('user_id', $userIds)->delete();
        WishlistItem::whereIn('user_id', $userIds)->delete();

        $this->seedCategories($user, $partner);
        $this->seedTransactions($user, $partner);
        $this->seedGoals($user, $partner);
        $this->seedWishlist($user, $partner);

        $this->command->info("Dados de teste criados para {$user->email} (família #{$family->id}, parceira: {$partner->email}).");
    }

    private function seedCategories(User $user, User $partner): void
    {
        $personal = [
            ['Alimentação', 1200], ['Transporte', 400], ['Lazer', 500],
            ['Saúde', 300], ['Assinaturas', 150],
        ];
        foreach ($personal as [$name, $limit]) {
            Category::create(['name' => $name, 'limit' => $limit, 'scope' => 'personal', 'user_id' => $user->id]);
        }

        $shared = [
            ['Mercado', 1500], ['Moradia', 2500], ['Contas Casa', 800], ['Viagens', 1000],
        ];
        foreach ($shared as [$name, $limit]) {
            Category::create(['name' => $name, 'limit' => $limit, 'scope' => 'shared', 'user_id' => $user->id]);
        }
    }

    private function seedTransactions(User $user, User $partner): void
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // ===== PESSOAL =====
        // Receita
        Transaction::create([
            'description' => 'Salário', 'type' => 'income', 'amount' => 6500,
            'category' => 'Salário', 'date' => $thisMonth->copy()->day(5),
            'scope' => 'personal', 'user_id' => $user->id,
        ]);
        Transaction::create([
            'description' => 'Freelance', 'type' => 'income', 'amount' => 1200,
            'category' => 'Extra', 'date' => now()->subDays(3),
            'scope' => 'personal', 'user_id' => $user->id,
        ]);

        // Despesas pessoais variadas
        $expenses = [
            ['Almoço restaurante', 'Alimentação', 48.90, now()],
            ['Mercado da esquina', 'Alimentação', 132.50, now()->subDay()],
            ['Uber', 'Transporte', 27.30, now()->subDay()],
            ['Gasolina', 'Transporte', 250, now()->subDays(4)],
            ['Cinema', 'Lazer', 70, now()->subDays(6)],
            ['Farmácia', 'Saúde', 89.90, now()->subDays(8)],
        ];
        foreach ($expenses as [$desc, $cat, $amt, $date]) {
            Transaction::create([
                'description' => $desc, 'type' => 'expense', 'amount' => $amt,
                'category' => $cat, 'date' => $date, 'scope' => 'personal', 'user_id' => $user->id,
            ]);
        }

        // Assinatura recorrente (Netflix) — este e mês passado
        $recGroup = (string) Str::uuid();
        foreach ([$lastMonth, $thisMonth] as $m) {
            Transaction::create([
                'description' => 'Netflix', 'type' => 'expense', 'amount' => 55.90,
                'category' => 'Assinaturas', 'date' => $m->copy()->day(10),
                'scope' => 'personal', 'user_id' => $user->id,
                'is_recurring' => true, 'recurring_group_id' => $recGroup,
            ]);
        }

        // Compra parcelada (Notebook 3x)
        $instGroup = (string) Str::uuid();
        for ($i = 1; $i <= 3; $i++) {
            Transaction::create([
                'description' => "Notebook ({$i}/3)", 'type' => 'expense', 'amount' => 1500,
                'category' => 'Lazer', 'date' => $lastMonth->copy()->addMonths($i - 1)->day(15),
                'scope' => 'personal', 'user_id' => $user->id,
                'is_installment' => true, 'installment_current' => $i, 'installment_count' => 3,
                'recurring_group_id' => $instGroup,
            ]);
        }

        // Reserva / poupança (savings)
        Transaction::create([
            'description' => 'Reserva emergência', 'type' => 'savings', 'amount' => 500,
            'category' => 'Poupança', 'date' => $thisMonth->copy()->day(6),
            'scope' => 'personal', 'user_id' => $user->id,
        ]);

        // ===== COMPARTILHADO (casal) =====
        Transaction::create([
            'description' => 'Aluguel', 'type' => 'expense', 'amount' => 2200,
            'category' => 'Moradia', 'date' => $thisMonth->copy()->day(5),
            'scope' => 'shared', 'user_id' => $user->id,
        ]);
        Transaction::create([
            'description' => 'Compra do mês', 'type' => 'expense', 'amount' => 870.40,
            'category' => 'Mercado', 'date' => now()->subDays(2),
            'scope' => 'shared', 'user_id' => $partner->id,
        ]);
        Transaction::create([
            'description' => 'Conta de luz', 'type' => 'expense', 'amount' => 180.75,
            'category' => 'Contas Casa', 'date' => now()->subDays(5),
            'scope' => 'shared', 'user_id' => $user->id,
        ]);
        Transaction::create([
            'description' => 'Internet', 'type' => 'expense', 'amount' => 99.90,
            'category' => 'Contas Casa', 'date' => now()->subDays(5),
            'scope' => 'shared', 'user_id' => $partner->id,
        ]);
        // Aporte conjunto (receita compartilhada)
        Transaction::create([
            'description' => 'Aporte conta conjunta', 'type' => 'income', 'amount' => 3000,
            'category' => 'Aporte', 'date' => $thisMonth->copy()->day(5),
            'scope' => 'shared', 'user_id' => $user->id,
        ]);
    }

    private function seedGoals(User $user, User $partner): void
    {
        Goal::create([
            'name' => 'Reserva de emergência', 'target' => 15000, 'current' => 6200,
            'scope' => 'personal', 'is_private' => false, 'user_id' => $user->id,
        ]);
        Goal::create([
            'name' => 'Notebook novo', 'target' => 5000, 'current' => 1500,
            'scope' => 'personal', 'is_private' => true, 'user_id' => $user->id,
        ]);
        Goal::create([
            'name' => 'Viagem Europa', 'target' => 25000, 'current' => 8400,
            'scope' => 'shared', 'is_private' => false, 'user_id' => $user->id,
        ]);
        Goal::create([
            'name' => 'Entrada apartamento', 'target' => 60000, 'current' => 12000,
            'scope' => 'shared', 'is_private' => false, 'user_id' => $partner->id,
        ]);
    }

    private function seedWishlist(User $user, User $partner): void
    {
        $items = [
            ['iPhone 16', 7500, 'high', 'personal', $user->id, 'pending'],
            ['Tênis de corrida', 650, 'medium', 'personal', $user->id, 'pending'],
            ['Headphone bluetooth', 400, 'low', 'personal', $user->id, 'purchased'],
            ['Sofá novo', 3200, 'high', 'shared', $user->id, 'pending'],
            ['Air fryer', 550, 'medium', 'shared', $partner->id, 'pending'],
            ['Smart TV 65"', 4200, 'low', 'shared', $user->id, 'pending'],
        ];
        foreach ($items as [$name, $price, $priority, $scope, $uid, $status]) {
            WishlistItem::create([
                'name' => $name, 'price' => $price, 'priority' => $priority,
                'scope' => $scope, 'status' => $status, 'user_id' => $uid,
                'url' => 'https://example.com/'.Str::slug($name),
            ]);
        }
    }
}
