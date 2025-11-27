fetch("data/security-summary.json")
  .then(res => res.json())
  .then(data => {
    const tbody = document.querySelector("#summary-table tbody");

    data.images.forEach(img => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${img.name}</td>
        <td>${img.critical}</td>
        <td>${img.high}</td>
        <td>${img.medium}</td>
      `;
      tbody.appendChild(row);
    });

    new Chart(document.getElementById("trendChart"), {
      type: "line",
      data: {
        labels: data.trend.map(t => t.date),
        datasets: [
          {
            label: "CRITICAL",
            data: data.trend.map(t => t.critical),
            borderColor: "red"
          },
          {
            label: "HIGH",
            data: data.trend.map(t => t.high),
            borderColor: "orange"
          },
          {
            label: "MEDIUM",
            data: data.trend.map(t => t.medium),
            borderColor: "gold"
          }
        ]
      }
    });
  });
