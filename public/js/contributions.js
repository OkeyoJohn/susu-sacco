
const form = document.getElementById("contribution-form");
const memberSelect = document.getElementById("member-id");
const amountInput = document.getElementById("amount");
const message = document.getElementById("message");
const balanceList = document.getElementById("balance-list");

const weekDisplay = document.getElementById("current-week");
const weeklyAmountDisplay = document.getElementById("weekly-amount");

const API_URL = "../api/contributions.php";

function formatMoney(amount) {
    return "KSh " + Number(amount).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

async function loadBalances() {
    try {
        const response = await fetch(API_URL);
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not load balances.");
        }

    weekDisplay.textContent = data.week_number;

const weeklyAmount =
    data.weekly_contribution_kes ??
    data.weekly_contribution_ghs ??
    (data.weekly_contribution_pesewas !== undefined
        ? Number(data.weekly_contribution_pesewas) / 100
        : null);

if (weeklyAmount === null || !Number.isFinite(Number(weeklyAmount))) {
    weeklyAmountDisplay.textContent = "Unable to load";
} else {
    weeklyAmountDisplay.textContent = formatMoney(weeklyAmount);
}

        memberSelect.replaceChildren();

        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "Choose a member";
        memberSelect.appendChild(placeholder);

        balanceList.replaceChildren();

        if (data.members.length === 0) {
            memberSelect.disabled = true;

            const row = document.createElement("tr");
            const cell = document.createElement("td");
            cell.colSpan = 3;
            cell.textContent = "No members registered yet.";
            row.appendChild(cell);
            balanceList.appendChild(row);
            return;
        }

        memberSelect.disabled = false;

        data.members.forEach((member) => {
            const option = document.createElement("option");
            option.value = member.member_id;
            option.textContent = member.name;
            memberSelect.appendChild(option);

            const paid = member.paid_kes ??
                Number(member.paid_pesewas) / 100;

            const owed = member.owed_kes ??
                Number(member.owed_pesewas) / 100;

            const row = document.createElement("tr");

            [member.name, formatMoney(paid), formatMoney(owed)]
                .forEach((value) => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });

            balanceList.appendChild(row);
        });
    } catch (error) {
        message.textContent = error.message;
    }
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const memberId = memberSelect.value;
    const amount = amountInput.value.trim();

    if (!memberId) {
        message.textContent = "Please select a member.";
        return;
    }

    if (!amount || !Number.isFinite(Number(amount)) ||
        Number(amount) <= 0) {
        message.textContent = "Enter an amount greater than zero.";
        return;
    }

    const button = form.querySelector("button");
    button.disabled = true;
    message.textContent = "Recording payment...";

    try {
        const response = await fetch(API_URL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                member_id: Number(memberId),
                amount: amount
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Could not record payment.");
        }

       const remainingBalance =
    data.remaining_kes ??
    data.remaining_ghs ??
    data.remaining_ghs ??
    (data.remaining_pesewas !== undefined
        ? Number(data.remaining_pesewas) / 100
        : null);

if (
    remainingBalance === null ||
    !Number.isFinite(Number(remainingBalance))
) {
    message.textContent = data.message +
        " Please refresh the page to view the updated balance.";
} else {
    message.textContent =
        `${data.message} Remaining balance: ${
            formatMoney(remainingBalance)
        }.`;
}

        amountInput.value = "";
        await loadBalances();
    } catch (error) {
        message.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});

loadBalances();
