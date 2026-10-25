// Pedido da vitrine: o cliente escolhe itens e quantidades e a mensagem vai pronta
// para o WhatsApp da loja. Sem preço e sem servidor; a lista fica no navegador.
const CHAVE = 'fa-pedido';

function ler() {
    try {
        return JSON.parse(localStorage.getItem(CHAVE)) || {};
    } catch {
        return {};
    }
}

function gravar(pedido) {
    try {
        localStorage.setItem(CHAVE, JSON.stringify(pedido));
    } catch {
        // Navegador sem armazenamento: o pedido vale só até fechar a página.
    }
}

export function mensagem(loja, pedido) {
    const linhas = Object.entries(pedido).map(([item, qtd]) => `• ${qtd}x ${item}`);

    return [`Olá! Quero fazer um pedido na ${loja}:`, '', ...linhas, '', 'Endereço de entrega:', 'Forma de pagamento:'].join('\n');
}

function iniciar() {
    const barra = document.querySelector('[data-carrinho]');
    if (!barra) return;

    const flutuante = document.querySelector('[data-whatsapp-flutuante]');
    const itens = [...document.querySelectorAll('[data-carrinho-item]')];
    const existentes = new Set(itens.map((el) => el.dataset.carrinhoItem));
    // Item que saiu do catálogo desde a última visita não volta para o pedido.
    let pedido = Object.fromEntries(Object.entries(ler()).filter(([item, qtd]) => existentes.has(item) && qtd > 0));

    function desenhar() {
        for (const el of itens) {
            const qtd = pedido[el.dataset.carrinhoItem] || 0;
            el.querySelector('[data-qtd]').textContent = qtd;
            el.querySelector('[data-qtd]').classList.toggle('hidden', qtd === 0);
            el.querySelector('[data-menos]').classList.toggle('hidden', qtd === 0);
            el.querySelector('[data-menos]').classList.toggle('flex', qtd > 0);
            const mais = el.querySelector('[data-mais]');
            mais.textContent = qtd === 0 ? '+ Adicionar' : '+';
        }

        const total = Object.values(pedido).reduce((soma, qtd) => soma + qtd, 0);
        barra.hidden = total === 0;
        if (flutuante) flutuante.hidden = total > 0;
        barra.querySelector('[data-total]').textContent = total;
        barra.querySelector('[data-total-rotulo]').textContent = total === 1 ? 'item' : 'itens';
        barra.querySelector('[data-enviar]').href =
            `https://wa.me/${barra.dataset.whatsapp}?text=${encodeURIComponent(mensagem(barra.dataset.loja, pedido))}`;
        gravar(pedido);
    }

    for (const el of itens) {
        const item = el.dataset.carrinhoItem;
        el.querySelector('[data-mais]').addEventListener('click', () => {
            pedido[item] = (pedido[item] || 0) + 1;
            desenhar();
        });
        el.querySelector('[data-menos]').addEventListener('click', () => {
            pedido[item] = Math.max(0, (pedido[item] || 0) - 1);
            if (pedido[item] === 0) delete pedido[item];
            desenhar();
        });
    }

    barra.querySelector('[data-limpar]').addEventListener('click', () => {
        pedido = {};
        desenhar();
    });

    desenhar();
}

document.addEventListener('DOMContentLoaded', iniciar);

// Celular: a lista de categorias leva direto à seção escolhida.
document.addEventListener('DOMContentLoaded', () => {
    const lista = document.querySelector('[data-categorias]');
    lista?.addEventListener('change', () => {
        document.getElementById(lista.value)?.scrollIntoView({ behavior: 'smooth' });
        lista.value = '';
    });
});
